<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\Widget\OnUiPageInitializedEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;

/**
 * Allows to modify widgets, that show the object of this behavior: e.g. add buttons, etc.
 * 
 * ## Examples
 * 
 * ### Add a button to the table Administration > Metamodel > Connections
 * 
 * ```
 *  {
 *      "page_alias": "exface.core.connections",
 *      "add_buttons": [
 *          {"action_alias": "my.App.SomeAction"}
 *      ]
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class WidgetModifyingBehavior extends AbstractBehavior
{    
    private $pageSelectorString = null;
    
    private $widgetId = null;
    
    private $addButtonsUxon = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnUiPageInitializedEvent::getEventName(), [$this, 'handleUiPageInitialized'], $this->getPriority());
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(OnUiPageInitializedEvent::getEventName(), [$this, 'handleUiPageInitialized']);
        return $this;
    }
    
    public function handleUiPageInitialized(OnUiPageInitializedEvent $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        $page = $event->getPage();
        
        if ($this->pageSelectorString !== null && ! $page->is($this->pageSelectorString)) {
            return;
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));
        
        if ($this->widgetId === null) {
            $widget = $page->getWidgetRoot();
        } else {
            $widget = $page->getWidget($this->widgetId);
        }
        
        if ($this->addButtonsUxon !== null) {
            if (! $widget instanceof iHaveButtons) {
                throw new BehaviorRuntimeError($this, 'Cannot add buttons to widget ' . $widget->getId() . ' of page ' . $page->getAliasWithNamespace() . ': widget does not have buttons!');
            }
            foreach ($this->addButtonsUxon as $btnUxon) {
                $widget->addButton($widget->createButton($btnUxon));
            }
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
        return;
    }
    
    /**
     * Array of buttons to be added to the widget
     * 
     * @uxon-property add_buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     * @uxon-template [{"action_alias": ""}]
     * 
     * @param UxonObject $uxonArray
     * @return WidgetModifyingBehavior
     */
    protected function setAddButtons(UxonObject $uxonArray) : WidgetModifyingBehavior
    {
        $this->addButtonsUxon = $uxonArray;
        return $this;
    }
    
    /**
     * UI Page to be modified
     * 
     * @uxon-property page_alias
     * @uxon-type metamodel:page
     * 
     * @param string $aliasOrUid
     * @return WidgetModifyingBehavior
     */
    protected function setPageAlias(string $aliasOrUid) : WidgetModifyingBehavior
    {
        $this->pageSelectorString = $aliasOrUid;
        return $this;
    }
    
    /**
     * Id of widget to be modified (will be the root widget if left empty)
     * 
     * @uxon-property widget_id
     * @uxon-type string
     * 
     * @param string $id
     * @return WidgetModifyingBehavior
     */
    protected function setWidgetId(string $id) : WidgetModifyingBehavior
    {
        $this->widgetId = $id;
        return $this;
    }
}