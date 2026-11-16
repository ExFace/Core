<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\Log\LogEntry;
use exface\Core\CommonLogic\Log\Monolog\DebugMessageMonologHandler;
use exface\Core\CommonLogic\Workbench;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * Exercises detail persistence with the real file manager, without starting the workbench.
 */
class DebugMessageFileWriteTest extends TestCase
{
    private string $directory;

    private Filemanager $filemanager;

    private DebugMessageMonologHandler $handler;

    /**
     * Creates an isolated destination and real file manager.
     *
     * {@inheritDoc}
     * @see TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exface-details-' . bin2hex(random_bytes(8));
        $workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'debug-details-unit-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        $this->filemanager = $workbench->filemanager();
        $this->handler = new DebugMessageMonologHandler($workbench, $this->directory, 'entry', '.json', Level::Debug);
    }

    /**
     * Removes only this test's isolated destination.
     *
     * {@inheritDoc}
     * @see TestCase::tearDown()
     */
    protected function tearDown(): void
    {
        $this->filemanager->remove($this->directory);
        parent::tearDown();
    }

    /**
     * The standard writer creates missing directories and persists complete JSON.
     *
     * @return void
     */
    public function testWritesDetailsInMissingDirectory(): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'entry.json';
        $json = '{"widget_type":"DebugMessage","tabs":[]}';
        $this->publish($path, $json);
        $this->assertSame($json, file_get_contents($path));
        $this->assertSame(['entry.json'], array_values(array_diff(scandir(dirname($path)), ['.', '..'])));
    }

    /**
     * Duplicate log entries keep existing details without rendering or appending again.
     *
     * @return void
     */
    public function testExistingDetailsAreNotOverwritten(): void
    {
        $entry = new LogEntry('error', 'Original error');
        $path = $this->directory . DIRECTORY_SEPARATOR . $entry->getId() . '.json';
        $original = '{"widget_type":"DebugMessage","caption":"Existing details"}';
        $this->filemanager->dumpFile($path, $original);
        $this->handler->handle(new LogRecord(
            new \DateTimeImmutable(),
            'Workbench',
            Level::Error,
            'Different message',
            ['entry' => $entry]
        ));
        $this->assertSame($original, file_get_contents($path));
    }

    /**
     * Invalid JSON is rejected before creating the destination.
     *
     * @return void
     */
    public function testInvalidJsonDoesNotCreateFile(): void
    {
        try {
            $this->publish($this->directory . DIRECTORY_SEPARATOR . 'entry.json', '{invalid');
            $this->fail('Invalid JSON must throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('rendered content is not valid JSON', $e->getMessage());
            $this->assertDirectoryDoesNotExist($this->directory);
        }
    }

    /**
     * A file blocking the destination directory produces an explicit filesystem error.
     *
     * @return void
     */
    public function testWriteFailurePropagates(): void
    {
        $this->filemanager->dumpFile($this->directory, 'blocking file');
        $this->expectException(IOExceptionInterface::class);
        $this->publish($this->directory . DIRECTORY_SEPARATOR . 'entry.json', '{}');
    }

    /**
     * Exercises the protected persistence boundary without invoking metamodel-dependent rendering.
     *
     * @param string $path
     * @param string $json
     * @return void
     */
    private function publish(string $path, string $json): void
    {
        $method = new \ReflectionMethod(DebugMessageMonologHandler::class, 'publish');
        $method->invoke($this->handler, $path, $json);
    }
}
