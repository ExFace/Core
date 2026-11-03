<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\CommonLogic\Log\Monolog\CsvFormatter;
use exface\Core\CommonLogic\Log\Processors\ActionAliasProcessor;
use exface\Core\CommonLogic\Log\Processors\IdProcessor;
use exface\Core\CommonLogic\Log\Processors\RequestIdProcessor;
use exface\Core\CommonLogic\Log\Processors\UserNameProcessor;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Logger;
use Monolog\Handler\GroupHandler;
use exface\Core\CommonLogic\Log\Monolog\DebugMessageMonologHandler;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\CommonLogic\Log\Processors\ContextFilterProcessor;
use Monolog\Handler\ErrorLogHandler;

/**
 * Logs to the main workbench CSV log and places debug widget JSONs in a separate folder.
 * 
 * What exactly is being logged depends on the log level thresholds provided in the constructor.
 * The logger will accumulate all messages with level `$ignoreBelowLevel` or above, but discard
 * them if no message of at least level `$persistAllAfterLevel` is received. If such a trigger
 * message arrives, all accumulated messages are dumped to the log.
 * 
 * Additionally, the `$passthroughLevel` allows to log messages starting with this level regularly
 * without triggering the total dump.
 * 
 * @author andrej.kabachnik
 *
 */
class MonologCsvFileHandler implements LogHandlerInterface
{

    private string $channelName;

    private string $csvFilePath;

    private ?string $debugMsgDirPath;
    
    private WorkbenchInterface $workbench;

    private string $minLevel;
    
    private string $persistLevel;
    
    private string $passthroughLevel;
    
    /** @var ?Logger $monolog */
    private ?Logger $monolog = null;
    
    private bool $disabled = false;

    /**
     *
     * @param WorkbenchInterface $workbench
     * @param string             $channelName
     * @param string             $csvFilePath
     * @param string|null        $debugMsgDirPath
     * @param string             $ignoreBelowLevel completely ignore messages below this level
     * @param string             $persistAllAfterLevel dump all accumulated messages when a message with at least this level is received
     * @param string             $passthroughLevel log messages with this level or above regularly (only them, not the accumulated ones)
     */
    public function __construct(
        WorkbenchInterface $workbench,
        string $channelName,
        string $csvFilePath,
        ?string $debugMsgDirPath = null,
        string $ignoreBelowLevel = LoggerInterface::DEBUG,
        string $persistAllAfterLevel = LoggerInterface::ALERT,
        string $passthroughLevel = LoggerInterface::ERROR
    )
    {
        $this->channelName = $channelName;
        $this->csvFilePath = $csvFilePath;
        $this->workbench = $workbench;
        $this->minLevel = $ignoreBelowLevel;
        $this->persistLevel = $persistAllAfterLevel;
        $this->passthroughLevel = $passthroughLevel;
        $this->debugMsgDirPath = $debugMsgDirPath;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle(
        $level,
        $message,
        array $context = [],
        iCanGenerateDebugWidgets $sender = null
    ) : void
    {
        // Ignore message, when disabled.
        if ($this->disabled === true) {
            return;
        }
        
        // Overwrite sender, when explicitly defined.
        if ($sender !== null) {
            $context['sender'] = $sender;
        }
        
        // Use PHP escape and log the message.
        $message = str_replace('\\', '\\\\', $message);
        $this->getMonolog()->log($level, $message, $context);
    }
    
    /**
     * Returns the logger for this instance.
     * 
     * Lazily instantiates the logger, when this function is called for the first time.
     * 
     * @return Logger
     */
    protected function getMonolog() : Logger
    {
        // Instantiate log handler, when NULL.
        if ($this->monolog === null) {
            $this->monolog = new Logger($this->channelName);
            try {
                // Create csv log handler.
                $csvHandler = new CsvHandler($this->csvFilePath, $this->minLevel);
                $csvHandler->setFormatter(new CsvFormatter()); // Date format will be derived from CsvLogConfig.
                
                $handlers = [$csvHandler];
                
                // Debug widget JSONs are optional: without a directory, only the CSV log is written.
                if ($this->debugMsgDirPath !== null) {
                    $dbgMsgHandler = new DebugMessageMonologHandler(
                        $this->workbench, 
                        'sender',
                        $this->debugMsgDirPath, 
                        'id',
                        '.json', 
                        Logger::toMonologLevel($this->minLevel)->value
                    );
                    $dbgMsgHandler->pushProcessor(new IdProcessor());
                    $handlers[] = $dbgMsgHandler;
                }
                
                // Bundle handlers. They are called in order.
                $grpHandler = new GroupHandler($handlers);
                
                if ($this->persistLevel === LoggerInterface::DEBUG) {
                    // Without buffering, every record is written immediately, so processors on the CsvHandler
                    // run at log time. Note that processors are applied in reverse order (LIFO stack).
                    $this->pushContextProcessors($csvHandler);
                    $csvHandler->pushProcessor($this->createContextFilterProcessor());
                    
                    // If all messages should be persisted, register the handlers as is.
                    $this->monolog->pushHandler($grpHandler);
                } else {
                    // If we only want to log certain messages, we wrap everything in a FingersCrossedHandler,
                    // which serves as a dynamic buffer.
                    $fcHandler = new FingersCrossedHandler(
                        $grpHandler,
                        new ErrorLevelActivationStrategy(Logger::toMonologLevel($this->persistLevel)),
                        0,
                        true,
                        true,
                        Logger::toMonologLevel($this->passthroughLevel)
                    );
                    
                    // Timing: processors on the FingersCrossedHandler run at log time, when the record enters the
                    // buffer. Processors on the inner handlers only run when the buffer is flushed, which may be
                    // much later (e.g. after the user or the current action changed). Time-sensitive values
                    // (request ID, user, action) are therefore captured on the FingersCrossedHandler.
                    // TODO geb 2026-10-26: This means these processors run on ALL logged records above the logging level. If performance becomes an issue, consider caching.
                    $this->pushContextProcessors($fcHandler);
                    // Timing: the context filter must run on the CsvHandler (at flush time), NOT on the
                    // FingersCrossedHandler. The DebugMessageMonologHandler in the same group still needs `sender`
                    // and `exception` to render the debug widget. Side effect: buffered records keep references
                    // to these objects until flushed or discarded at the end of the request.
                    $csvHandler->pushProcessor($this->createContextFilterProcessor());
                    
                    // Push the wrapper.
                    $this->monolog->pushHandler($fcHandler);
                }
            } catch (\Throwable $e) {
                $this->monolog->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::toMonologLevel($this->minLevel)));
                $this->monolog->critical('Failed to initialize log handler "MonologCsvFileHandler": ' . $e->getMessage(), ['exception' => $e]);
            }
        }
            
        return $this->monolog;
    }

    /**
     * Pushes `IdProcessor`, `RequestIdProcessor`, `UsernameProcessor`, `ActionAliasProcessor` onto the
     * stack of the provided handler (in that order).
     * 
     * @param ProcessableHandlerInterface $handler
     * @return void
     */
    private function pushContextProcessors(ProcessableHandlerInterface $handler) : void
    {
        $handler->pushProcessor(new IdProcessor());
        $handler->pushProcessor(new RequestIdProcessor($this->workbench));
        $handler->pushProcessor(new UsernameProcessor($this->workbench));
        $handler->pushProcessor(new ActionAliasProcessor($this->workbench));
    }

    /**
     * Creates the processor that strips non-serializable objects (`exception`, `sender`) from the CSV context.
     * 
     * Must only be pushed onto the CsvHandler, because the debug message handler still needs these keys.
     * 
     * @return ContextFilterProcessor
     */
    private function createContextFilterProcessor() : ContextFilterProcessor
    {
        return new ContextFilterProcessor(['exception', 'sender']);
    }
    
    /**
     * 
     * @param bool $trueOrFalse
     * @return MonologCsvFileHandler
     */
    public function setDisabled(bool $trueOrFalse) : MonologCsvFileHandler
    {
        $this->disabled = $trueOrFalse;
        return $this;
    }
}