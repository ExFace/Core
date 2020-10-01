<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * Event fired after when the default editor UXON of an object is initialized.
 * 
 * Listeners to this event can modify the UXON before it is actually processed by
 * any code using it. Any modificiations will remain within the object and will
 * still be in placed, if `MetaObject::getDefaultEditorUxon()` is called multiple
 * times.
 * 
 * @event exface.Core.Model.OnBeforeDefaultObjectEditorInit
 *
 * @author Andrej Kabachnik
 *
 */
class OnBeforeDefaultObjectEditorInitEvent extends AbstractEvent implements MetaObjectEventInterface
{
    
    private $object = null;
    
    private $uxon = null;
    
    /**
     * 
     * @param MetaObjectInterface $object
     */
    public function __construct(MetaObjectInterface $object, UxonObject $uxon)
    {
        $this->object = $object;
        $this->uxon = $uxon;
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
     * @return UxonObject
     */
    public function getDefaultEditorUxon() : UxonObject
    {
        return $this->uxon;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Model.OnBeforeDefaultObjectEditorInit';
    }
}