<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Event fired before a meta object's modeled action isn instantiated.
 * 
 * Listeners to this even can modify the UXON configuration of the action.
 * 
 * @event exface.Core.Model.OnBeforeMetaObjectActionLoaded
 *
 * @author Andrej Kabachnik
 *
 */
class OnBeforeMetaObjectActionLoadedEvent extends AbstractEvent implements MetaObjectEventInterface
{
    private $prototype = null;
    
    private $actionAlias = null;
    
    private $app = null;
    
    private $object = null;
    
    private $uxon = null;
    
    private $triggerWidget = null;
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param ActionInterface $action
     */
    public function __construct(string $prototype, string $actionAlias, AppInterface $app, MetaObjectInterface $object, UxonObject $uxon = null, WidgetInterface $triggerWidget)
    {
        $this->object = $object;
        $this->actionAlias = $actionAlias;
        $this->app = $app;
        $this->object = $object;
        $this->uxon = $uxon;
        $this->triggerWidget = $triggerWidget;
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
     * @see \exface\Core\Events\AbstractEvent::getAlias()
     */
    public function getActionAlias() : string
    {
        return $this->actionAlias;
    }
    
    /**
     * 
     * @return AppInterface
     */
    public function getApp() : AppInterface
    {
        return $this->app;
    }
    
    /**
     * 
     * @return UxonObject|NULL
     */
    public function getUxon() : ?UxonObject
    {
        return $this->uxon;
    }
    
    /**
     * 
     * @return WidgetInterface|NULL
     */
    public function getTriggerWidget() : ?WidgetInterface
    {
        return $this->widget;
    }
}