<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;

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

    private $uid = null;
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param ActionInterface $action
     */
    public function __construct(string $prototype, string $actionAlias, AppInterface $app, MetaObjectInterface $object, UxonObject $uxon = null, WidgetInterface $triggerWidget = null, string $uid = null)
    {
        $this->prototype = $prototype;
        $this->object = $object;
        $this->actionAlias = $actionAlias;
        $this->app = $app;
        $this->object = $object;
        $this->uxon = $uxon;
        $this->triggerWidget = $triggerWidget;
        $this->uid = $uid;
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
     * @return string
     */
    public function getPrototype() : string
    {
        return $this->prototype;
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
    public function getActionAlias() : string
    {
        return $this->actionAlias;
    }
    
    /**
     * 
     * @return string
     */
    public function getActionAliasWithNamespace() : string
    {
        return $this->app->getAliasWithNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->getActionAlias();
    }

    public function getActionUid() : ?string
    {
        return $this->uid;
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
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Model.OnBeforeMetaObjectActionLoaded';
    }
}