<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Widgets\MessageList;
use exface\Core\Interfaces\Model\BehaviorInterface;

/**
 * Event fired after the model of an object behavior was validated.
 * 
 * Listeners to this even can perform additional validation and append the resulting messages to
 * the MessageList widget included in the event.
 * 
 * @event exface.Core.Model.OnBehaviorModelValidated
 *
 * @author Andrej Kabachnik
 *
 */
class OnBehaviorModelValidatedEvent extends AbstractEvent implements MetaObjectEventInterface
{
    private $messageList = null;
    
    private $behavior = null;
    
    public function __construct(BehaviorInterface $behavior, MessageList $messageList)
    {
        $this->messageList = $messageList;
        $this->behavior = $behavior;
    }
    
    /**
     * 
     * @return MessageList
     */
    public function getMessageList() : MessageList
    {
        return $this->messageList;
    }
    
    /**
     * 
     * @return BehaviorInterface
     */
    public function getBehavior() : BehaviorInterface
    {
        return $this->behavior;
    }
    
    
    public function getObject() : MetaObjectInterface
    {
        return $this->behavior->getObject();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->behavior->getWorkbench();
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Model.OnBehaviorModelValidated';
    }
}