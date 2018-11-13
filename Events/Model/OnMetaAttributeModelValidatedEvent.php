<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Widgets\MessageList;

/**
 * Event fired after the model of a meta object had been validated.
 * 
 * Listeners to this even can perform additional validation and append the resulting messages to
 * the MessageList widget included in the event.
 * 
 * @event exface.Core.Model.OnMetaAttributeModelValidated
 *
 * @author Andrej Kabachnik
 *
 */
class OnMetaAttributeModelValidatedEvent extends AbstractEvent implements MetaObjectEventInterface
{
    private $messageList = null;
    
    private $attribute = null;
    
    public function __construct(MetaAttributeInterface $attribute, MessageList $messageList)
    {
        $this->messageList = $messageList;
        $this->attribute = $attribute;
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
     * @return MetaAttributeInterface
     */
    public function getAttribute() : MetaAttributeInterface
    {
        return $this->attribute;
    }
    
    
    public function getObject() : MetaObjectInterface
    {
        return $this->getAttribute()->getObject();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->attribute->getWorkbench();
    }
}