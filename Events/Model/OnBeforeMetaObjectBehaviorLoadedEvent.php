<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * Event fired before a meta objects behavior is instantiated.
 * 
 * Listeners to this even can modify the UXON configuration of the behavior.
 * 
 * @event exface.Core.Model.OnBeforeMetaObjectBehaviorLoaded
 *
 * @author Andrej Kabachnik
 *
 */
class OnBeforeMetaObjectBehaviorLoadedEvent extends AbstractEvent implements MetaObjectEventInterface
{
    private $prototype = null;
    
    private $behaviorUid = null;
    
    private $appUid = null;
    
    private $object = null;
    
    private $uxon = null;
    
    /**
     * 
     * @param string $prototype
     * @param string $behaviorUid
     * @param \exface\Core\Interfaces\AppInterface $appUid
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $object
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     */
    public function __construct(string $prototype, string $behaviorUid, string $appUid, MetaObjectInterface $object, UxonObject $uxon)
    {
        $this->prototype = $prototype;
        $this->object = $object;
        $this->behaviorUid = $behaviorUid;
        $this->appUid = $appUid;
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
     * @return string
     */
    public function getBehaviorUid() : string
    {
        return $this->behaviorUid;
    }

    /**
     * 
     * @return string
     */
    public function getPrototype() : string
    {
        return $this->prototype;
    }
    
    /**
     * 
     * @return AppInterface
     */
    public function getApp() : AppInterface
    {
        return $this->getWorkbench()->getApp($this->appUid);
    }
    
    /**
     * 
     * @return UxonObject
     */
    public function getUxon() : UxonObject
    {
        return $this->uxon;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Model.OnBeforeMetaObjectBehaviorLoaded';
    }
}