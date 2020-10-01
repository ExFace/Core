<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Events\ActionEventInterface;

/**
 * Event fired after a meta object's modeled action had been instantiated and it's model was loaded.
 * 
 * Listeners to this even can perform can modify properties of the action.
 * 
 * @event exface.Core.Model.OnMetaObjectActionLoaded
 *
 * @author Andrej Kabachnik
 *
 */
class OnMetaObjectActionLoadedEvent extends AbstractEvent implements MetaObjectEventInterface, ActionEventInterface
{
    private $action = null;
    
    private $object = null;
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param ActionInterface $action
     */
    public function __construct(MetaObjectInterface $object, ActionInterface $action)
    {
        $this->object = $object;
        $this->action = $action;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\MetaObjectEventInterface::getObject()
     */
    public function getObject() : MetaObjectInterface
    {
        return $this->object;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->object->getWorkbench();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\ActionEventInterface::getAction()
     */
    public function getAction() : ActionInterface
    {
        return $this->action;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Model.OnMetaObjectActionLoaded';
    }
}