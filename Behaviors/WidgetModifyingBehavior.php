<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Events\Widget\OnDataConfiguratorInitEvent;
use exface\Core\Events\Widget\OnUiActionWidgetInitEvent;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\Events\WidgetEventInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Widgets\DataTableConfigurator;
use exface\Core\Widgets\Dialog;

/**
 * Allows to modify widgets, that show the object of this behavior: e.g. add buttons, etc.
 * 
 * ## Examples
 * 
 * ### Add a button to the table Administration > Metamodel > Connections
 * 
 * ```
 *  {
 *      "only_page_roots": true,
 *      "only_pages": [
 *          "exface.core.connections"
 *      },
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
    private ?array $onlyOnPages = null;
    
    private ?array $onlyWidgetIds = null;

    private ?array $onlyForActions = null;

    private bool $onlyPageRoot = false;

    private ?array $onlyWidgetTypes = null;

    private ?UxonObject $buttonsToAddUxon = null;

    private ?UxonObject $columnsToAddUxon = null;

    private ?UxonObject $sideBarToAdd = null;

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnUiActionWidgetInitEvent::getEventName(), [$this, 'onUiActionWidgetInitialized'], $this->getPriority());
        $this->getWorkbench()->eventManager()->addListener(OnDataConfiguratorInitEvent::getEventName(), [$this, 'onDataConfiguratorInitialized'], $this->getPriority());
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(OnUiActionWidgetInitEvent::getEventName(), [$this, 'onUiActionWidgetInitialized']);
        $this->getWorkbench()->eventManager()->removeListener(OnDataConfiguratorInitEvent::getEventName(), [$this, 'onDataConfiguratorInitialized'], $this->getPriority());
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

    /**
     * 
     * @param \exface\Core\Interfaces\Model\UiPageInterface $page
     * @return bool
     */
    protected function isRelevantPage(UiPageInterface $page) : bool
    {
        if ($this->onlyOnPages === null) {
            return true;
        }
        foreach ($this->onlyOnPages as $selector) {
            if ($page->is($selector)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\WidgetInterface $widget
     * @return bool
     */
    protected function isRelevantForWidget(WidgetInterface $widget) : bool
    {
        if ($this->onlyPageRoot === true) {
            if ($widget->hasParent() === true) {
                return false;
            }
        }
        if ($this->onlyWidgetIds !== null) {
            if (in_array($widget->getId(), $this->onlyWidgetIds) === false) {
                return false;
            }
        }
        return true;
    }

    protected function isRelevantForAction(ActionInterface $action) : bool
    {
        if ($this->onlyForActions !== null) {
            $found = false;
            foreach ($this->onlyForActions as $actionSelector) {
                if ($action->is($actionSelector) === true) {
                    $found = true;
                }
            }
            return $found;
        }
        return true;
    }

    /**
     * 
     * @param \exface\Core\Events\Widget\OnDataConfiguratorInitEvent $event
     * @return void
     */
    public function onDataConfiguratorInitialized(OnDataConfiguratorInitEvent $event) : void
    {
        if ($this->columnsToAddUxon === null) {
            return;
        }

        if (! $this->isRelevant($event)) {
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
     * @param \exface\Core\Events\Widget\OnUiActionWidgetInitEvent $event
     * @return void
     */
    public function onUiActionWidgetInitialized(OnUiActionWidgetInitEvent $event) : void
    {
        if ($this->buttonsToAddUxon === null && $this->sideBarToAdd === null) {
            return;
        }

        if (! $this->isRelevant($event)) {
            return;
        }

        if ($this->onlyForActions !== null && ! $this->isRelevantForAction($event->getAction())) {
            return;
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));

        $widget = $event->getWidget();
        if ($this->buttonsToAddUxon !== null && $widget instanceof iHaveButtons) {
            $this->addButtonsToWidget($widget, $this->buttonsToAddUxon);
        }

        if ($this->sideBarToAdd !== null && $widget instanceof Dialog) {
            $widget->setSidebar($this->sideBarToAdd);
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
    }

    /**
     * 
     * @param \exface\Core\Interfaces\Events\WidgetEventInterface $event
     * @return bool
     */
    protected function isRelevant(WidgetEventInterface $event) : bool
    {
        
        if ($this->isDisabled()) {
            return false;
        }

        $page = $event->getPage();
        if (! $this->isRelevantPage($page)) {
            return false;
        }

        $widget = $event->getWidget();
        if (! $widget->getMetaObject()->isExactly($this->getObject())) {
            return false;
        }

        return $this->isRelevantForWidget($widget);
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
     * Add a sidebar to the widgets if they support sidebars
     * 
     * @uxon-property add_sidebar
     * @uxon-type \exface\Core\Widgets\DialogSidebar
     * 
     * @param \exface\Core\CommonLogic\UxonObject $sidebarUxon
     * @return \exface\Core\Behaviors\WidgetModifyingBehavior
     */
    protected function setAddSidebar(UxonObject $sidebarUxon) : WidgetModifyingBehavior
    {
        $this->sideBarToAdd = $sidebarUxon;
        return $this;
    }
    
    /**
     * Only apply modification to widgets on these pages
     * 
     * @uxon-property only_on_pages
     * @uxon-type metamodel:page[]
     * @uxon-template [""]
     * 
     * @param string $aliasOrUid
     * @return WidgetModifyingBehavior
     */
    protected function setOnlyOnPages(UxonObject $aliasOrUids) : WidgetModifyingBehavior
    {
        $this->onlyOnPages = $aliasOrUids->toArray();
        return $this;
    }

    /**
     * @deprecated use setOnlyOnPages() / only_on_pages instead
     * @param \exface\Core\CommonLogic\UxonObject $aliasOrUids
     * @return \exface\Core\Behaviors\WidgetModifyingBehavior
     */
    protected function setOnlyPages(UxonObject $aliasOrUids) : WidgetModifyingBehavior
    {
        return $this->setOnlyOnPages($aliasOrUids);
    }

    /**
     * @deprecated use setOnlyOnPages() / only_on_pages instead
     * @param string $aliasOrUid
     * @return \exface\Core\Behaviors\WidgetModifyingBehavior
     */
    protected function setPageAlias(string $aliasOrUid) : WidgetModifyingBehavior
    {
        return $this->setOnlyOnPages(new UxonObject([$aliasOrUid]));
    }

    /**
     * Set to `TRUE` to only modify the root widgets of pages
     * 
     * @uxon-property only_page_roots
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return \exface\Core\Behaviors\WidgetModifyingBehavior
     */
    protected function setOnlyPageRoots(bool $trueOrFalse) : WidgetModifyingBehavior
    {
        $this->onlyPageRoot = $trueOrFalse;
        return $this;
    }
    
    /**
     * Only modify widgets with the following ids
     * 
     * @uxon-property only_widget_ids
     * @uxon-type string[]
     * 
     * @param string $id
     * @return WidgetModifyingBehavior
     */
    protected function setOnlyWidgetIds(UxonObject $arrayOfIds) : WidgetModifyingBehavior
    {
        $this->onlyWidgetIds = $arrayOfIds->toArray();
        return $this;
    }

    /**
     * @deprecated use setOnlyWidgetIds / only_widget_ids instead
     * 
     * @param string $id
     * @return \exface\Core\Behaviors\WidgetModifyingBehavior
     */
    protected function setWidgetId(string $id) : WidgetModifyingBehavior
    {
        return $this->setOnlyWidgetIds(new UxonObject([$id]));
    }

    /**
     * Only modify widgets opened by the following actions
     * 
     * @uxon-property only_for_actions
     * @uxon-type metamodel:action[]
     * @uxon-template [""]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $arrayOfAliases
     * @return \exface\Core\Behaviors\WidgetModifyingBehavior
     */
    protected function setOnlyForActions(UxonObject $arrayOfAliases) : WidgetModifyingBehavior
    {
        $this->onlyForActions = $arrayOfAliases->toArray();
        return $this;
    }
}