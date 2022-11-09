<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\CommonLogic\Log\Handlers\MonologErrorLogHandler;
use Monolog\ErrorHandler;
use exface\Core\CommonLogic\Log\Logger;
use exface\Core\CommonLogic\Log\Handlers\MonologCsvFileHandler;
use exface\Core\DataTypes\DateDataType;
use exface\Core\CommonLogic\Log\Handlers\LimitingHandler;
use exface\Core\Events\Workbench\OnCleanUpEvent;
use exface\Core\CommonLogic\Log\LogCleaner;

/**
 * Instantiates default loggers
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class LoggerFactory extends AbstractStaticFactory
{
    /** @var Logger $logger */
    private static $logger = null;
    
    private static $fallbackLogger = null;
    
    /**
     * Instantiates the default CSV logger with debug widget in separate files
     * 
     * @param WorkbenchInterface $workbench
     * @return LoggerInterface
     */
    public static function createDefaultLogger(WorkbenchInterface $workbench) : LoggerInterface
    {
        if (! static::$logger) {
            static::$logger = new Logger();
            try {
                $handlers = [];
                $coreLogDir = $workbench->filemanager()->getPathToLogFolder();
                $coreLogFileExt = 'log';
                $date = DateDataType::now();
                $coreLogFilePath = $coreLogDir . DIRECTORY_SEPARATOR . $date . '.' . $coreLogFileExt;
                $detailsLogDir = $workbench->filemanager()->getPathToLogDetailsFolder() . DIRECTORY_SEPARATOR . $date;
                
                $config             = $workbench->getConfig();
                $minLogLevel        = $config->getOption('LOG.MINIMUM_LEVEL_TO_LOG');
                $passthroughLevel   = $config->getOption('LOG.PASSTHROUGH_LOG_LEVEL');
                $persistLevel       = $config->getOption('LOG.PERSIST_LOG_LEVEL');
                $minLogLevel        = $config->getOption('LOG.MINIMUM_LEVEL_TO_LOG');
                
                $handlers[] = new MonologCsvFileHandler( // Init the actual logger
                    $workbench, 
                    "Workbench", 
                    $coreLogFilePath, 
                    $detailsLogDir, 
                    $minLogLevel, 
                    $persistLevel, 
                    $passthroughLevel
                );
                
                foreach ($handlers as $handler) {
                    static::$logger->appendHandler($handler);
                }
            } catch (\Throwable $t) {
                static::$logger = static::createPhpErrorLogLogger();
                static::$logger->critical('Log initialisation failed: ' . $t->getMessage(), [
                    'exception' => $t
                ]);
            }
        }
        $workbench->eventManager()->addListener(OnCleanUpEvent::getEventName(), [
            LogCleaner::class,
            'onCleanUp'
        ]);
        return static::$logger;
    }
    
    /**
     * Registers error logger at monolog ErrorHandler.
     *
     * @param WorkbenchInterface $workbench
     * @return void
     */
    public static function registerErrorLogger(WorkbenchInterface $workbench)
    {
        ErrorHandler::register(static::getErrorLogger($workbench));
    }
    
    /**
     * Instantiates a logger dumping everything to the PHP error_log
     * 
     * @return LoggerInterface
     */
    public static function createPhpErrorLogLogger() : LoggerInterface
    {
        if (static::$fallbackLogger === null) {
            static::$fallbackLogger = new Logger();
            static::$fallbackLogger->appendHandler(new MonologErrorLogHandler("Workbench"));
        }
        return static::$fallbackLogger;
    }
}