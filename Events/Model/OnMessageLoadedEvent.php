<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\MessageEventInterface;
use exface\Core\Interfaces\Model\MessageInterface;

/**
 * Event fired after the model of a message was loaded.
 * 
 * Listeners to this even can perform can modify properties of the message.
 * 
 * @event exface.Core.Model.OnMessageLoaded
 *
 * @author Andrej Kabachnik
 *
 */
class OnMessageLoadedEvent extends AbstractEvent implements MessageEventInterface
{
    
    private $message = null;
    
    /**
     * 
     * @param MessageInterface $message
     */
    public function __construct(MessageInterface $message)
    {
        $this->message = $message;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\MessageEventInterface::getMessage()
     */
    public function getMessage() : MessageInterface
    {
        return $this->message;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->message->getWorkbench();
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Model.OnMessageLoaded';
    }
}