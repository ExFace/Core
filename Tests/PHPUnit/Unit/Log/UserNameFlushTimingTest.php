<?php
namespace exface\Core\Tests\PHPUnit\Unit\Log;

use exface\Core\CommonLogic\ContextManager;
use exface\Core\CommonLogic\EventManager;
use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\CommonLogic\Log\Handlers\MonologCsvFileHandler;
use exface\Core\CommonLogic\Log\LogEntry;
use exface\Core\CommonLogic\Security\AuthenticationToken\UsernamePasswordAuthToken;
use exface\Core\CommonLogic\Security\SecurityManager;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Verifies flush-time identity without running authentication or database-backed Workbench startup.
 */
class UserNameFlushTimingTest extends TestCase
{
    /**
     * Early records use the user available at flush time while keeping their original log ID.
     */
    public function testBufferedRecordUsesFinalUserAndRetainsLogId(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exface-user-flush-'
            . bin2hex(random_bytes(8)) . '.v1.log';
        $workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'user-flush-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        $events = (new \ReflectionClass(EventManager::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(EventManager::class, 'dispatcher'))->setValue($events, new EventDispatcher());
        (new \ReflectionProperty(Workbench::class, 'event_manager'))->setValue($workbench, $events);
        $handler = new MonologCsvFileHandler(
            $workbench,
            'Test',
            $path,
            null,
            LoggerInterface::DEBUG,
            LoggerInterface::ERROR,
            LoggerInterface::NOTICE
        );
        $entry = new LogEntry(LoggerInterface::NOTICE, 'Before authentication');

        try {
            $handler->handle($entry);
            self::assertFileDoesNotExist($path);

            // Seed only the token accessor: constructing SecurityManager would load the persisted metamodel.
            $security = (new \ReflectionClass(SecurityManager::class))->newInstanceWithoutConstructor();
            (new \ReflectionProperty(SecurityManager::class, 'authenticatedToken'))->setValue(
                $security,
                new UsernamePasswordAuthToken('final-user', '')
            );
            (new \ReflectionProperty(Workbench::class, 'security'))->setValue($workbench, $security);
            (new \ReflectionProperty(Workbench::class, 'context'))->setValue($workbench, new ContextManager($workbench));
            (new \ReflectionProperty(Workbench::class, 'started'))->setValue($workbench, true);
            $monolog = (new \ReflectionProperty(MonologCsvFileHandler::class, 'monolog'))->getValue($handler);
            $monolog->close();

            $stream = fopen($path, 'r');
            try {
                $row = array_combine(CsvLogConfig::getColumns('v1'), fgetcsv($stream, 0, ',', '"', ''));
                self::assertSame('final-user', $row[CsvLogConfig::KEY_USER_NAME]);
                self::assertSame($entry->getId(), $row[CsvLogConfig::KEY_ID]);
                self::assertSame('Before authentication', $row[CsvLogConfig::KEY_MESSAGE]);
                self::assertFalse(fgetcsv($stream, 0, ',', '"', ''));
            } finally {
                fclose($stream);
            }
        } finally {
            (new \ReflectionProperty(Workbench::class, 'started'))->setValue($workbench, false);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
