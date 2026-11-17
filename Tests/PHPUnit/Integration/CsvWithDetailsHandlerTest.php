<?php
namespace exface\Core\Tests\PHPUnit\Integration;

use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\CommonLogic\Log\Handlers\CsvHandler;
use exface\Core\CommonLogic\Log\Handlers\CsvWithDetailsHandler;
use exface\Core\CommonLogic\Log\Monolog\CsvFormatter;
use exface\Core\DataTypes\ServerSoftwareDataType;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * Verifies CSV persistence and real OS account diagnostics (whoami on Windows).
 */
class CsvWithDetailsHandlerTest extends TestCase
{
    /** @var resource */
    private $stream;

    /**
     * Creates an isolated CSV destination.
     *
     * {@inheritDoc}
     * @see TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->stream = fopen('php://memory', 'w+');
    }

    /**
     * Releases the CSV destination even after failed assertions.
     *
     * {@inheritDoc}
     * @see TestCase::tearDown()
     */
    protected function tearDown(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        parent::tearDown();
    }

    /**
     * Failed details preserve the original entry, add an OS-user diagnostic and do not stop the batch.
     *
     * @return void
     */
    public function testFailedDetailsPreserveRowsAndReportOsUser(): void
    {
        $first = $this->record('ABC12345', 'Original failure');
        $next = $this->record('DEF67890', 'Next entry');
        $details = $this->createMock(HandlerInterface::class);
        $details->method('isHandling')->willReturn(true);
        $details->expects($this->exactly(2))->method('handle')->willReturnCallback(
            function (LogRecord $record): bool {
                if ($record->message === 'Original failure') {
                    throw new \RuntimeException('Cannot create temp file: folder not writable.');
                }
                return false;
            }
        );
        $handler = new CsvWithDetailsHandler($details, $this->csvHandler());
        $handler->handleBatch([$first, $next]);

        $rows = $this->rows();
        $this->assertCount(3, $rows);
        $this->assertSame('ABC12345', $rows[0][CsvLogConfig::KEY_ID]);
        $this->assertSame('Original failure [Writing log details failed.]', $rows[0][CsvLogConfig::KEY_MESSAGE]);
        $this->assertSame('app-user', $rows[0][CsvLogConfig::KEY_USER_NAME]);
        $this->assertSame(['original' => 'context'], json_decode($rows[0][CsvLogConfig::KEY_CONTEXT], true));
        $this->assertNotSame('ABC12345', $rows[1][CsvLogConfig::KEY_ID]);
        $this->assertNotEmpty($rows[1][CsvLogConfig::KEY_ID]);
        $this->assertSame('ERROR', $rows[1][CsvLogConfig::KEY_LEVEL_NAME]);
        $this->assertSame(ServerSoftwareDataType::getOsUser() ?? '(unknown OS user)', $rows[1][CsvLogConfig::KEY_USER_NAME]);
        $this->assertStringContainsString('ABC12345', $rows[1][CsvLogConfig::KEY_MESSAGE]);
        $this->assertStringContainsString($rows[1][CsvLogConfig::KEY_USER_NAME], $rows[1][CsvLogConfig::KEY_MESSAGE]);
        $this->assertStringContainsString('folder not writable', $rows[1][CsvLogConfig::KEY_MESSAGE]);
        $this->assertSame('request-1', $rows[1][CsvLogConfig::KEY_REQUEST_ID]);
        $this->assertSame('exface.Core.ReadData', $rows[1][CsvLogConfig::KEY_ACTION_ALIAS]);
        $this->assertSame([], json_decode($rows[1][CsvLogConfig::KEY_CONTEXT], true));
        $this->assertSame('DEF67890', $rows[2][CsvLogConfig::KEY_ID]);
        $this->assertSame('Next entry', $rows[2][CsvLogConfig::KEY_MESSAGE]);
        $this->assertSame('Original failure', $first->message);
    }

    /**
     * Successful detail writes keep the original message and produce no diagnostic row.
     *
     * @return void
     */
    public function testSuccessfulDetailsKeepSingleUnchangedRow(): void
    {
        $details = $this->createMock(HandlerInterface::class);
        $details->method('isHandling')->willReturn(true);
        $details->expects($this->once())->method('handle')->willReturn(false);
        $handler = new CsvWithDetailsHandler($details, $this->csvHandler(), false);
        $this->assertTrue($handler->handle($this->record('ABC12345', 'Original message')));
        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertSame('Original message', $rows[0][CsvLogConfig::KEY_MESSAGE]);
    }

    /**
     * Broken CSV destinations must continue to propagate errors to the logger fallback.
     *
     * @return void
     */
    public function testCsvFailureStillPropagates(): void
    {
        $details = $this->createMock(HandlerInterface::class);
        $details->method('isHandling')->willReturn(true);
        $details->method('handle')->willReturn(false);
        $csv = $this->createMock(CsvHandler::class);
        $csv->method('isHandling')->willReturn(true);
        $csv->method('handle')->willThrowException(new \RuntimeException('CSV not writable'));
        $handler = new CsvWithDetailsHandler($details, $csv);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CSV not writable');
        $handler->handle($this->record('ABC12345', 'Original message'));
    }

    /**
     * Creates a real CSV writer with the production formatter.
     *
     * @return CsvHandler
     */
    private function csvHandler(): CsvHandler
    {
        $handler = new CsvHandler($this->stream);
        $handler->setFormatter(new CsvFormatter());
        return $handler;
    }

    /**
     * Creates a record with captured application identity and correlation metadata.
     *
     * @param string $id
     * @param string $message
     * @return LogRecord
     */
    private function record(string $id, string $message): LogRecord
    {
        return new LogRecord(
            new \DateTimeImmutable(),
            'Workbench',
            Level::Critical,
            $message,
            ['original' => 'context'],
            [
                CsvLogConfig::KEY_ID => $id,
                CsvLogConfig::KEY_USER_NAME => 'app-user',
                CsvLogConfig::KEY_REQUEST_ID => 'request-1',
                CsvLogConfig::KEY_ACTION_ALIAS => 'exface.Core.ReadData'
            ]
        );
    }

    /**
     * Parses persisted rows using the same versioned layout as the log reader.
     *
     * @return array[]
     */
    private function rows(): array
    {
        rewind($this->stream);
        $rows = [];
        $version = CsvLogConfig::CURRENT_VERSION;
        while (($row = fgetcsv(
            $this->stream,
            0,
            CsvLogConfig::getDelimiter($version),
            CsvLogConfig::getEnclosure($version),
            CsvLogConfig::getEscape($version)
        )) !== false) {
            $rows[] = array_combine(CsvLogConfig::getColumns($version), $row);
        }
        return $rows;
    }
}
