<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Events\Widget\OnDataConfiguratorInitEvent;
use exface\Core\Events\Widget\OnUiRootWidgetInitEvent;
use exface\Core\Interfaces\Model\BehaviorInterface;
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

    private bool $onlyPageRoot = false;

    private ?array $onlyWidgetTypes = null;

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

    protected function isRelevantForWidget(WidgetInterface $widget) : bool
    {
        if ($this->onlyPageRoot === true) {
            return ! $widget->hasParent();
        }
        if ($this->onlyWidgetIds === null) {
            return true;
        }
        foreach ($this->onlyWidgetIds as $id) {
            if ($widget->getId() === $id) {
                return true;
            }
        }
        return false;
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
        if (! $this->isRelevantForWidget($widget)) {
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
     * Only apply modification to widgets on these pages
     * 
     * @uxon-property only_pages
     * @uxon-type metamodel:page[]
     * @uxon-template [""]
     * 
     * @param string $aliasOrUid
     * @return WidgetModifyingBehavior
     */
    protected function setOnlyPages(UxonObject $aliasOrUids) : WidgetModifyingBehavior
    {
        $this->onlyOnPages = $aliasOrUids->toArray();
        return $this;
    }

    /**
     * @deprecated  use setOnlyPages() / only_pages instead
     * @param string $aliasOrUid
     * @return \exface\Core\Behaviors\WidgetModifyingBehavior
     */
    protected function setPageAlias(string $aliasOrUid) : WidgetModifyingBehavior
    {
        return $this->setOnlyPages(new UxonObject([$aliasOrUid]));
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
}