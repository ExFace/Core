<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\Interfaces\Log\LogEntryInterface;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\DataTypes\BooleanDataType;

/**
 * Buffers all log entries to pass them on to the actual handler at the end of the request.
 * 
 * The $handler passed in __construct() will get all log entries en block once
 * the buffering handler is destroyed - unless it was disabled somewhere along
 * the way.
 * 
 * @author Andrej Kabachnik
 *
 */
class BufferingHandler implements LogHandlerInterface
{

    private $handler = null;
    
    private $disabled = false;
    
    /** @var LogEntryInterface[] */
    private array $entries = [];
    
    /**
     * 
     * @param LogHandlerInterface $handler
     * @param string $minLevel
     * @param string $maxLevel
     */
    public function __construct(LogHandlerInterface $handler)
    {
        $this->handler = $handler;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle(LogEntryInterface $entry) : void
    {
        $this->entries[] = $entry;
    }
    
    /**
     * Force-dump all buffered messages to the inner logger
     * 
     * @return void
     */
    public function flush()
    {
        foreach ($this->entries as $entry){
            $this->handler->handle($entry);
        }
        $this->entries = [];
    }
    
    public function __destruct()
    {
        if (! $this->isDisabled()){
            $this->flush();
        }
    }
    
    /**
     * 
     * @return boolean
     */
    public function isDisabled()
    {
        return $this->disabled;
    }
    
    /**
     * If disabled, the handler will not pass it's entries anywhere
     * 
     * @param boolean $true_or_false
     */
    public function setDisabled($true_or_false)
    {
        $this->disabled = BooleanDataType::cast($true_or_false);
    }
}
