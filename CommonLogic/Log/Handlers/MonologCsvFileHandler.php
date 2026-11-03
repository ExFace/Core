<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\CommonLogic\Log\Monolog\CsvFormatter;
use exface\Core\CommonLogic\Log\Processors\ActionAliasProcessor;
use exface\Core\CommonLogic\Log\Processors\IdProcessor;
use exface\Core\CommonLogic\Log\Processors\RequestIdProcessor;
use exface\Core\CommonLogic\Log\Processors\UserNameProcessor;
use exface\Core\Interfaces\Log\LogEntryInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Logger;
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
    // Monolog only accepts array contexts, so the log entry travels through Monolog under this key. Processors
    // and handlers below receive the key via constructor, the CSV context filter removes it again.
    private const CONTEXT_KEY_LOG_ENTRY = 'logEntry';
    
    // Defined by PSR-3: lets Monolog format the stack trace
    private const PSR_KEY_EXCEPTION = 'exception';

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
    public function handle(LogEntryInterface $entry) : void
    {
        // Ignore message, when disabled.
        if ($this->disabled === true) {
            return;
        }
        
        $context = $entry->getContext();
        $context[self::CONTEXT_KEY_LOG_ENTRY] = $entry;
        
        // Use PHP escape and log the message.
        $message = str_replace('\\', '\\\\', $entry->getMessage());
        $this->getMonolog()->log($entry->getLevel(), $message, $context);
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
                
                // The context filter strips the log entry from the CSV context only. It runs on the CsvHandler,
                // i.e. after the processors of all wrapping handlers - and after the detail handler, which still
                // needs the entry to render the debug widget.
                $csvHandler->pushProcessor($this->createContextFilterProcessor());
                
                // Debug widget JSONs are optional: without a directory, only the CSV log is written.
                if ($this->debugMsgDirPath !== null) {
                    $dbgMsgHandler = new DebugMessageMonologHandler(
                        $this->workbench, 
                        $this->debugMsgDirPath, 
                        self::CONTEXT_KEY_LOG_ENTRY,
                        '.json', 
                        Logger::toMonologLevel($this->minLevel)
                    );
                    // Publishes the detail file before the CSV row, so a row never links to a missing file.
                    $writer = new CsvWithDetailsHandler($dbgMsgHandler, $csvHandler);
                } else {
                    $writer = $csvHandler;
                }
                
                if ($this->persistLevel === LoggerInterface::DEBUG) {
                    // Without buffering, every record is written immediately, so processors on the writer run at
                    // log time. Processors are applied in reverse order (LIFO stack): if the writer is the
                    // CsvHandler itself, the context filter pushed above still runs last - after the IdProcessor
                    // read the log entry.
                    $this->pushContextProcessors($writer);
                    
                    // If all messages should be persisted, register the writer as is.
                    $this->monolog->pushHandler($writer);
                } else {
                    // If we only want to log certain messages, we wrap everything in a FingersCrossedHandler,
                    // which serves as a dynamic buffer.
                    $fcHandler = new FingersCrossedHandler(
                        $writer,
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
                    // Timing: the context filter on the CsvHandler runs at flush time, NOT on the
                    // FingersCrossedHandler, because the DebugMessageMonologHandler still needs the log entry.
                    // Side effect: buffered records keep references to the entries (incl. exceptions and senders)
                    // until flushed or discarded at the end of the request.
                    
                    // Push the wrapper.
                    $this->monolog->pushHandler($fcHandler);
                }
            } catch (\Throwable $e) {
                $this->monolog->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::toMonologLevel($this->minLevel)));
                $this->monolog->critical('Failed to initialize log handler "MonologCsvFileHandler": ' . $e->getMessage(), [self::PSR_KEY_EXCEPTION => $e]);
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
        $handler->pushProcessor(new IdProcessor(self::CONTEXT_KEY_LOG_ENTRY));
        $handler->pushProcessor(new RequestIdProcessor($this->workbench));
        $handler->pushProcessor(new UsernameProcessor($this->workbench));
        $handler->pushProcessor(new ActionAliasProcessor($this->workbench));
    }

    /**
     * Creates the processor that strips the log entry object from the CSV context.
     * 
     * Must only be pushed onto the CsvHandler, because the debug message handler still needs the entry.
     * `CsvWithDetailsHandler` calls the debug message handler before the CsvHandler.
     * 
     * @return ContextFilterProcessor
     */
    private function createContextFilterProcessor() : ContextFilterProcessor
    {
        return new ContextFilterProcessor([self::CONTEXT_KEY_LOG_ENTRY]);
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