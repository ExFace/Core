<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Event fired after the meta object had been instantiated and it's model was loaded.
 * 
 * Listeners to this even can perform can modify properties of the object or add
 * attributes, relations, behaviors, etc.
 * 
 * @event exface.Core.Model.OnMetaObjectLoaded
 *
 * @author Andrej Kabachnik
 *
 */
class OnMetaObjectLoadedEvent extends AbstractEvent implements MetaObjectEventInterface
{
    
    private $object = null;
    
    /**
     * 
     * @param MetaObjectInterface $object
     */
    public function __construct(MetaObjectInterface $object)
    {
        $this->object = $object;
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
}