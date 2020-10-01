<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Widgets\MessageList;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;

/**
 * Event fired after the model of a meta object had been validated.
 * 
 * Listeners to this even can perform additional validation and append the resulting messages to
 * the MessageList widget included in the event.
 * 
 * @event exface.Core.Model.OnMetaObjectModelValidated
 *
 * @author Andrej Kabachnik
 *
 */
class OnMetaObjectModelValidatedEvent extends AbstractEvent implements MetaObjectEventInterface
{
    private $messageList = null;
    
    private $object = null;
    
    public function __construct(MetaObjectInterface $object, MessageList $messageList)
    {
        $this->messageList = $messageList;
        $this->object = $object;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Model.OnMetaObjectModelValidated';
    }
    
    public function getMessageList() : MessageList
    {
        return $this->messageList;
    }
    
    public function getObject() : MetaObjectInterface
    {
        return $this->object;
    }
    
    public function getWorkbench()
    {
        return $this->object->getWorkbench();
    }
}