<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\Widget\OnGlobalActionsAddedEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;

/**
 * Adds custom global actions for this object - similar to Excel export, favorites, etc.
 * 
 * ## Examples
 * 
 * ```
 *  {
 *      "buttons": [
 *          {"action_alias": "my.App.ShowImportantInfoDialog"}
 *      ]
 *  }
 * 
 * ```
 * 
 * You can also see this behavior in action in the metamodel administration: it adds the
 * technical information button for objects based on the `exface.Core.BASE_OBJECT`.
 * 
 * @author Andrej Kabachnik
 *
 */
class GlobalActionsBehavior extends AbstractBehavior
{
    private $buttonsUxon = null;
    
    private $widgetsProcessed = [];
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnGlobalActionsAddedEvent::getEventName(), [$this, 'onGlobalActionsAdded'], $this->getPriority());
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'onGlobalActionsAdded']);
        
        return $this;
    }
    
    /**
     * 
     * @param OnGlobalActionsAddedEvent $event
     * @return void
     */
    public function onGlobalActionsAdded(OnGlobalActionsAddedEvent $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        $obj = $event->getWidget()->getMetaObject();
        if (! $obj->isExactly($this->getObject())) {
            return;
        }
        
        $btnGrp = $event->getGlobalActionsButtongGroup();
        if (in_array($btnGrp, $this->widgetsProcessed, true) === true) {
            return;
        }
        
        foreach ($this->getButtonsUxon() as $uxon) {
            $btn = $btnGrp->createButton($uxon);
            if (! $uxon->hasProperty('visibility') && ! $btn->isHidden()) {
                $btn->setVisibility(WidgetVisibilityDataType::OPTIONAL);
            }
            $btnGrp->addButton($btn);
        }
        
        $this->widgetsProcessed[] = $btnGrp;
    }
    
    /**
     * 
     * @return UxonObject
     */
    protected function getButtonsUxon() : UxonObject
    {
        return $this->buttonsUxon ?? new UxonObject();
    }
    
    /**
     * Buttons to be added
     * 
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     * @uxon-template [{"action_alias": ""}]
     * 
     * @param UxonObject $value
     * @return GlobalActionsBehavior
     */
    protected function setButtons(UxonObject $value) : GlobalActionsBehavior
    {
        $this->buttonsUxon = $value;
        return $this;
    }
}