<?php
namespace exface\Core\Tests\PHPUnit\Unit\Log;

use exface\Core\CommonLogic\ContextManager;
use exface\Core\CommonLogic\EventManager;
use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\CommonLogic\Log\Handlers\MonologCsvFileHandler;
use exface\Core\CommonLogic\Log\LogEntry;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Verifies buffered request identity using real local context objects without starting Workbench.
 */
class RequestIdFlushTimingTest extends TestCase
{
    /**
     * Shutdown passthrough resolves early records against the final request ID, not their initial context.
     */
    public function testBufferedRecordsUseRequestIdAtFlushTime(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exface-request-id-'
            . bin2hex(random_bytes(8)) . '.v1.log';
        $workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'request-id-flush-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        $context = new ContextManager($workbench);
        $events = (new \ReflectionClass(EventManager::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(EventManager::class, 'dispatcher'))->setValue($events, new EventDispatcher());
        (new \ReflectionProperty(Workbench::class, 'event_manager'))->setValue($workbench, $events);
        (new \ReflectionProperty(Workbench::class, 'context'))->setValue($workbench, $context);
        $scope = $context->getScopeRequest();
        $initialId = $scope->getRequestId();
        $handler = new MonologCsvFileHandler(
            $workbench,
            'Test',
            $path,
            null,
            LoggerInterface::DEBUG,
            LoggerInterface::ERROR,
            LoggerInterface::NOTICE
        );

        try {
            $handler->handle(new LogEntry(LoggerInterface::NOTICE, 'Early request'));
            $scope->setRequestId('external-request-id');
            $handler->handle(new LogEntry(LoggerInterface::NOTICE, 'Later request'));
            self::assertFileDoesNotExist($path);

            // Only request-context access is needed for flushing; do not start database-backed services.
            (new \ReflectionProperty(Workbench::class, 'started'))->setValue($workbench, true);
            $monolog = (new \ReflectionProperty(MonologCsvFileHandler::class, 'monolog'))->getValue($handler);
            $monolog->close();

            $stream = fopen($path, 'r');
            try {
                $rows = [];
                while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                    $rows[] = array_combine(CsvLogConfig::getColumns('v1'), $row);
                }
            } finally {
                fclose($stream);
            }

            self::assertNotSame('external-request-id', $initialId);
            self::assertCount(2, $rows);
            self::assertSame(['Early request', 'Later request'], array_column($rows, CsvLogConfig::KEY_MESSAGE));
            self::assertSame(
                ['external-request-id', 'external-request-id'],
                array_column($rows, CsvLogConfig::KEY_REQUEST_ID)
            );
        } finally {
            (new \ReflectionProperty(Workbench::class, 'started'))->setValue($workbench, false);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
