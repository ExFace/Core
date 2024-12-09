<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Events\Widget\OnDataConfiguratorInitialized;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\Widget\OnUiPageInitializedEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Widgets\DataColumnDeferred;
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

    private int $columnsDefaultVisibility = WidgetVisibilityDataType::OPTIONAL;

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnUiPageInitializedEvent::getEventName(), [$this, 'handleUiPageInitialized'], $this->getPriority());
        $this->getWorkbench()->eventManager()->addListener(OnDataConfiguratorInitialized::getEventName(), [$this, 'handleDataConfiguratorInitialized'], $this->getPriority());
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

    protected function getTargetWidget(?string $widgetId, UiPageInterface $page) : WidgetInterface
    {
        return $widgetId === null ?
            $page->getWidgetRoot() : $page->getWidget($widgetId);
    }

    protected function addButtonsToWidget(WidgetInterface $widget, ?UxonObject $buttonsUxon) : void
    {
        if(!isset($buttonsUxon) || !$widget instanceof iHaveButtons) {
            return;
        }

        foreach ($buttonsUxon as $btnUxon) {
            $widget->addButton($widget->createButton($btnUxon));
        }
    }

    protected function addColumnsToConfigurator(DataTableConfigurator $configurator, ?UxonObject $columnsUxon) : void
    {
        foreach($columnsUxon as $columnUxon){
            $column = $configurator->createColumnFromUxon($columnUxon);
            if(!$columnUxon->getProperty('visibility')){
                $column->setVisibility($this->columnsDefaultVisibility);
            }

            $configurator->addColumn($column);
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

    public function handleDataConfiguratorInitialized(OnDataConfiguratorInitialized $event) : void
    {
        if ($this->isDisabled()) {
            return;
        }

        if(!$this->isRelevantPage($event->getWidget()->getPage())) {
            return;
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));

        $configurator = $event->getWidget();
        if($configurator instanceof DataTableConfigurator) {
            $this->addColumnsToConfigurator($configurator, $this->columnsToAddUxon);
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
    }

    public function handleUiPageInitialized(OnUiPageInitializedEvent $event) : void
    {
        if ($this->isDisabled()) {
            return;
        }

        $page = $event->getPage();
        if (!$this->isRelevantPage($page)) {
            return;
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));

        $widget = $this->getTargetWidget($this->widgetId, $page);
        $this->addButtonsToWidget($widget, $this->buttonsToAddUxon);
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
    }

    /**
     * Sets the default visibility of added columns: normal, hidden, optional, promoted.
     *
     * You can still adjust visibility per added column. Such manual overrides have priority.
     *
     * @uxon-property visibility
     * @uxon-type [normal,promoted,optional,hidden]
     * @uxon-default optional
     *
     */
    public function setColumnsDefaultVisibility($value) : BehaviorInterface
    {
        if (is_int($value)){
            $this->columnsDefaultVisibility = $value;
        } else {
            if (! defined('EXF_WIDGET_VISIBILITY_' . mb_strtoupper($value))) {
                throw new BehaviorConfigurationError($this, 'Invalid visibility value "' . $value . '" for behavior "' . $this->getName() . '"!');
            }
            $this->columnsDefaultVisibility = constant('EXF_WIDGET_VISIBILITY_'. mb_strtoupper($value));
        }
        return $this;
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