<?php
namespace exface\Core\CommonLogic\Log;

use exface\Core\Interfaces\Debug\IHaveLogIdInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Log\LogEntryInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Immutable log entry created by the `Logger` from the PSR-3 arguments of a log call.
 * 
 * Resolves the exception, sender, level and log ID once, so handlers do not need to know how they are
 * passed in the PSR-3 context.
 * 
 * @author Georg Bieger
 */
class LogEntry implements LogEntryInterface
{
    // Defined by PSR-3: "If an Exception object is passed in the context data, it MUST be in the 'exception' key."
    private const PSR_KEY_EXCEPTION = 'exception';
    
    private string $level;
    
    private string $message;
    
    private string $id;
    
    private ?\Throwable $exception;
    
    private ?iCanGenerateDebugWidgets $sender;
    
    private array $context;
    
    /**
     * 
     * @param string|null $level PSR-3 level - if NULL, the level of an exface exception or `ERROR` is used
     * @param string $message
     * @param array $psrContext PSR-3 context - may contain an exception in the `exception` key
     * @param iCanGenerateDebugWidgets|null $sender
     */
    public function __construct(?string $level, string $message, array $psrContext = [], ?iCanGenerateDebugWidgets $sender = null)
    {
        $exception = $psrContext[self::PSR_KEY_EXCEPTION] ?? null;
        if ($exception instanceof \Throwable) {
            unset($psrContext[self::PSR_KEY_EXCEPTION]);
        } else {
            // Not a valid PSR-3 exception - leave it in the context as regular data
            $exception = null;
        }
        
        if ($sender === null && $exception instanceof iCanGenerateDebugWidgets) {
            $sender = $exception;
        }
        
        if ($sender instanceof ExceptionInterface) {
            $exception = $sender;
            $level = $level ?? $sender->getLogLevel();
        }
        
        $this->level = $level ?? LoggerInterface::ERROR;
        $this->message = $message;
        $this->exception = $exception;
        $this->sender = $sender;
        $this->context = $psrContext;
        $this->id = $sender instanceof IHaveLogIdInterface ? $sender->getLogId() : Logger::generateLogId();
    }
    
    /**
     * {@inheritDoc}
     * @see LogEntryInterface::getLevel()
     */
    public function getLevel() : string
    {
        return $this->level;
    }
    
    /**
     * {@inheritDoc}
     * @see LogEntryInterface::getMessage()
     */
    public function getMessage() : string
    {
        return $this->message;
    }
    
    /**
     * {@inheritDoc}
     * @see LogEntryInterface::getId()
     */
    public function getId() : string
    {
        return $this->id;
    }
    
    /**
     * {@inheritDoc}
     * @see LogEntryInterface::getException()
     */
    public function getException() : ?\Throwable
    {
        return $this->exception;
    }
    
    /**
     * {@inheritDoc}
     * @see LogEntryInterface::getSender()
     */
    public function getSender() : ?iCanGenerateDebugWidgets
    {
        return $this->sender;
    }
    
    /**
     * {@inheritDoc}
     * @see LogEntryInterface::getContext()
     */
    public function getContext() : array
    {
        return $this->context;
    }
}
