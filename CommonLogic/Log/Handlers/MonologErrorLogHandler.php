<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\Interfaces\Log\LogEntryInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use Monolog\Logger;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use Monolog\Handler\ErrorLogHandler;

/**
 * Logs to PHP error_log via Monolog
 * 
 * @author Andrej Kabachnik
 *
 */
class MonologErrorLogHandler implements LogHandlerInterface
{
    // PSR-3 context keys of the Monolog record - only labels in the error log line. `exception` is defined
    // by PSR-3 and lets Monolog format the stack trace.
    private const PSR_KEY_EXCEPTION = 'exception';
    private const PSR_KEY_ID = 'id';
    
    /** @var Logger $monolog */
    private $monolog = null;
    
    private $channelName;
    
    private $minLevel;

    /**
     * 
     * @param string $channelName
     * @param string $ignoreBelowLevel
     */
    public function __construct(string $channelName, string $ignoreBelowLevel = LoggerInterface::CRITICAL)
    {
        $this->channelName = $channelName;
        $this->minLevel = $ignoreBelowLevel;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle(LogEntryInterface $entry) : void
    {
        $context = $entry->getContext();
        $context[self::PSR_KEY_ID] = $entry->getId();
        if ($entry->getException() !== null) {
            $context[self::PSR_KEY_EXCEPTION] = $entry->getException();
        }
        $this->getMonolog()->log($entry->getLevel(), $entry->getMessage(), $context);
    }
    
    /**
     * 
     * @return Logger
     */
    protected function getMonolog() : Logger
    {
        if ($this->monolog === null) {
            $this->monolog = new Logger($this->channelName);            
            $this->monolog->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::toMonologLevel($this->minLevel)));
        }
            
        return $this->monolog;
    }
}
