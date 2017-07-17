<?php
namespace exface\Core\CommonLogic\Log\Handlers;

use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\DataTypes\BooleanDataType;

/**
 * Buffers all log entries to pass them on to the actual handler at the end of the request.
 * 
 * The handler passed in __construct() will get all log entries en block once
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
    
    private $messages = [];
    
    /**
     * 
     * @param LogHandlerInterface $handler
     * @param string $minLevel
     * @param string $maxLevel
     */
    function __construct(LogHandlerInterface $handler)
    {
        $this->handler = $handler;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle($level, $message, array $context = array(), iCanGenerateDebugWidgets $sender = null)
    {
        $this->messages[] = array("level" => $level, "message" => $message, "context" => $context, "sender" => $sender);
    }
    
    public function __destruct(){
        if (! $this->isDisabled()){
            foreach ($this->messages as $message){
                $this->handler->handle($message['level'], $message['message'], $message['context'], $message['sender']);
            }
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
        $this->disabled = BooleanDataType::parse($true_or_false);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->handler->getWorkbench();
    }
}
