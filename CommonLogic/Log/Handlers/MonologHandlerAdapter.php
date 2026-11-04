<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\CommonLogic\Log\Processors\ContextFilterProcessor;
use exface\Core\CommonLogic\Log\Processors\IdProcessor;
use exface\Core\Interfaces\Log\LogEntryInterface;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

/**
 * Makes any Monolog handler (e.g. third-party handlers for Sentry, Slack, Graylog) usable as a workbench log handler.
 * 
 * Use it next to the `MonologCsvFileHandler`, not inside it:
 * 
 * ```
 * $logger->appendHandler(new MonologHandlerAdapter('Workbench', new SlackWebhookHandler(...)));
 * ```
 * 
 * Converts each `LogEntryInterface` into a regular PSR-3 / Monolog record, so the wrapped handlers find
 * everything where Monolog conventions expect it:
 * 
 * - message and level as usual
 * - the exception in `context['exception']` (as defined by PSR-3) - used by most handlers and formatters to
 *   render stack traces or report errors
 * - the log ID in `extra['id']`, so it can be matched with the workbench log
 * - the caller-provided context
 * 
 * The entry itself is removed from the context after all processors ran, so formatters do not serialize it.
 * Processors pushed via `pushProcessor()` still can read it from `context[LogEntryInterface::MONOLOG_CONTEXT_KEY]`
 * (e.g. to access the sender). Pass `$keepLogEntryInContext = true` if a wrapped handler needs it too.
 * 
 * Workbench-specific data (request ID, user, action) is not added by default. Push the respective processors
 * (`RequestIdProcessor`, `UserNameProcessor`, `ActionAliasProcessor`) if needed. They run at log time, which
 * is correct even if the wrapped handler buffers records.
 * 
 * Security: the exception and the context are passed on unfiltered. Make sure the wrapped handlers do not
 * send sensitive data (e.g. stack traces with arguments) to untrusted destinations.
 * 
 * @author GitHub Copilot, Georg Bieger
 */
class MonologHandlerAdapter implements LogHandlerInterface
{
    // Defined by PSR-3: "If an Exception object is passed in the context data, it MUST be in the 'exception' key."
    private const PSR_KEY_EXCEPTION = 'exception';
    
    private Logger $monolog;
    
    /**
     * @param string $channelName Monolog channel
     * @param HandlerInterface|HandlerInterface[] $handlers Monolog handlers to pass the entries to
     * @param bool $keepLogEntryInContext TRUE to pass the entry to the handlers too
     */
    public function __construct(string $channelName, HandlerInterface|array $handlers, bool $keepLogEntryInContext = false)
    {
        $this->monolog = new Logger($channelName, is_array($handlers) ? $handlers : [$handlers]);
        
        // Processors are a LIFO stack: pushed first, the filter runs last - after all processors that read the entry.
        if ($keepLogEntryInContext === false) {
            $this->monolog->pushProcessor(new ContextFilterProcessor([LogEntryInterface::MONOLOG_CONTEXT_KEY]));
        }
        $this->monolog->pushProcessor(new IdProcessor(LogEntryInterface::MONOLOG_CONTEXT_KEY));
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle(LogEntryInterface $entry) : void
    {
        $context = $entry->getContext();
        if ($entry->getException() !== null) {
            $context[self::PSR_KEY_EXCEPTION] = $entry->getException();
        }
        $context[LogEntryInterface::MONOLOG_CONTEXT_KEY] = $entry;
        
        $this->monolog->log($entry->getLevel(), $entry->getMessage(), $context);
    }
    
    /**
     * Adds a Monolog processor, that runs for every entry before the wrapped handlers.
     * 
     * The processor runs before the log entry is removed from the context, so it can read it.
     * 
     * @param callable $processor
     * @return MonologHandlerAdapter
     */
    public function pushProcessor(callable $processor) : MonologHandlerAdapter
    {
        $this->monolog->pushProcessor($processor);
        return $this;
    }
    
    /**
     * Returns the wrapped Monolog handlers.
     * 
     * @return HandlerInterface[]
     */
    public function getHandlers() : array
    {
        return $this->monolog->getHandlers();
    }
}