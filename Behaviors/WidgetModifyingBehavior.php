<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Events\Widget\OnDataConfiguratorInitEvent;
use exface\Core\Events\Widget\OnUiRootWidgetInitEvent;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\Widget\OnUiPageInitEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Widgets\DataTableConfigurator;

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
    private ?string $pageSelectorString = null;
    
    private ?string $widgetId = null;

    private ?UxonObject $buttonsToAddUxon = null;

    private ?UxonObject $columnsToAddUxon = null;

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnUiRootWidgetInitEvent::getEventName(), [$this, 'handleUiRootInitialized'], $this->getPriority());
        $this->getWorkbench()->eventManager()->addListener(OnDataConfiguratorInitEvent::getEventName(), [$this, 'handleDataConfiguratorInitialized'], $this->getPriority());
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(OnUiRootWidgetInitEvent::getEventName(), [$this, 'handleUiRootInitialized']);
        $this->getWorkbench()->eventManager()->removeListener(OnDataConfiguratorInitEvent::getEventName(), [$this, 'handleDataConfiguratorInitialized'], $this->getPriority());
        return $this;
    }

    /**
     * 
     * @param iHaveButtons $widget
     * @param UxonObject $buttonsUxon
     * @return void
     */
    protected function addButtonsToWidget(iHaveButtons $widget, UxonObject $buttonsUxon) : void
    {
        foreach ($buttonsUxon as $btnUxon) {
            $widget->addButton($widget->createButton($btnUxon));
        }
    }

    protected function isRelevantPage(?UiPageInterface $page) : bool
    {
        if(!isset($page)){
            return false;
        }

        return $this->pageSelectorString === null ||
            $page->is($this->pageSelectorString);
    }

    /**
     * 
     * @param \exface\Core\Events\Widget\OnDataConfiguratorInitEvent $event
     * @return void
     */
    public function handleDataConfiguratorInitialized(OnDataConfiguratorInitEvent $event) : void
    {
        if ($this->isDisabled()) {
            return;
        }

        if ($this->columnsToAddUxon === null) {
            return;
        }
        
        if (! $event->getObject()->isExactly($this->getObject())) {
            return;
        }

        if(!$this->isRelevantPage($event->getWidget()->getPage())) {
            return;
        }

        $configurator = $event->getWidget();
        if(! ($configurator instanceof DataTableConfigurator)) {
            return;
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));
        $configurator->setOptionalColumns( $this->columnsToAddUxon);
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
    }

    /**
     * 
     * @param \exface\Core\Events\Widget\OnUiPageInitEvent $event
     * @return void
     */
    public function handleUiRootInitialized(OnUiRootWidgetInitEvent $event) : void
    {
        if ($this->isDisabled()) {
            return;
        }

        $page = $event->getPage();
        if (! $this->isRelevantPage($page)) {
            return;
        }

        $widget = $event->getWidget();
        if (! $widget->getMetaObject()->isExactly($this->getObject())) {
            return;
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));

        if ($this->buttonsToAddUxon !== null && $widget instanceof iHaveButtons) {
            $this->addButtonsToWidget($widget, $this->buttonsToAddUxon);
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
    }

    /**
     * Array of columns to be added to the widget
     *
     * @uxon-property add_columns
     * @uxon-type \exface\Core\Widgets\DataColumn[]
     * @uxon-template [{"attribute_alias": ""}]
     *
     * @param UxonObject $uxonArray
     * @return WidgetModifyingBehavior
     */
    protected function setAddColumns(UxonObject $uxonArray) : WidgetModifyingBehavior
    {
        $this->columnsToAddUxon = $uxonArray;
        return $this;
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
        $this->buttonsToAddUxon = $uxonArray;
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