<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\DataTypes\RegularExpressionDataType;
use exface\Core\Events\Widget\OnDataConfiguratorInitEvent;
use exface\Core\Events\Widget\OnUiActionWidgetInitEvent;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Events\WidgetEventInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Interfaces\Widgets\iHaveSidebar;
use exface\Core\Widgets\DataTableConfigurator;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;

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
 * ## Columns added via `add_columns`
 * 
 * Since the same behavior instance can apply to many different widgets showing its object, a
 * column configured in `add_columns` may collide with a column, that already exists on a
 * particular widget (added by the widget's own definition or by another behavior instance) -
 * same attribute or calculation, but possibly a different visibility, caption, etc. In this
 * case the pre-existing column always wins and is left untouched: `add_columns` only ever adds
 * columns that are not there yet, it never reconfigures existing ones.
 * 
 * A column added to a widget, that is aggregated (grouped), also needs to be usable in that
 * context - either matching one of the grouping attributes or having an aggregator (e.g. `SUM`,
 * `MAX`) - otherwise reading aggregated data would fail. Since `add_columns` cannot know in
 * advance, whether a widget it applies to is aggregated, it automatically appends the
 * attribute's `default_aggregate_function` to columns, that would otherwise be neither grouped
 * nor aggregated.
 * 
 * @author Andrej Kabachnik
 *
 */
class WidgetModifyingBehavior extends AbstractBehavior
{    
    private ?array $onlyOnPages = null;
    private ?array $onlyOnPagesRegex = null;
    private ?array $onlyWidgetIds = null;
    private bool $onlyPageRoot = false;
    private ?array $onlyForActions = null;
    
    private ?UxonObject $propertiesToSet = null;
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
        foreach ($this->onlyOnPagesRegex as $pattern) {
            $pageAlias = $page->getAliasWithNamespace();
            if (preg_match($pattern, $pageAlias) === 1) {
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
                if ($action->isExactly($actionSelector) === true) {
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
        // TODO avoid infinite loops here when there are no optional columns to add
        // For example, adding ` && $this->propertiesToSet === null ` caused infinite loops when opening LOG_ENTRY in the
        // logs. The behavior added a Sidebar with an AiChat widget, but no optional columns. No idea, why
        // that caused trouble, but for now `change_properties` simply only works for regular widgets, not for
        // configurators.
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
        
        $existingExpressions = $this->getExistingColumnExpressions($configurator);
        $columnsUxon = new UxonObject();
        foreach ($this->columnsToAddUxon->toArray() as $column) {
            $expr = $column['attribute_alias'] ?? $column['calculation'] ?? null;
            if ($expr !== null && in_array($expr, $existingExpressions, true)) {
                continue;
            }
            $column = $this->applyAggregatorIfNeeded($configurator, $column);
            $columnsUxon->append($column);
        }
        if($configurator->hasOptionalColumns()) {
            foreach ($configurator->getOptionalColumnsUxon()->toArray() as $column) {
                $columnsUxon->append($column);
            }
        }        
        $configurator->setOptionalColumns($columnsUxon);
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
    }

    /**
     * Returns the attribute aliases / calculation expressions of all columns already present on the
     * configured widget or already registered as optional columns on the configurator.
     *
     * @param \exface\Core\Widgets\DataTableConfigurator $configurator
     * @return string[]
     */
    protected function getExistingColumnExpressions(DataTableConfigurator $configurator) : array
    {
        $expressions = [];
        foreach ($configurator->getWidgetConfigured()->getColumns() as $existingColumn) {
            if ($alias = $existingColumn->getAttributeAlias()) {
                $expressions[] = $alias;
            } elseif ($existingColumn->isCalculated() && ! $existingColumn->getCalculationExpression()->isEmpty()) {
                $expressions[] = $existingColumn->getCalculationExpression()->__toString();
            }
        }
        if ($configurator->hasOptionalColumns()) {
            // Use the raw uxon here, not getOptionalColumns() - that would lazily (and permanently)
            // build the columns tab from the not-yet-final columnsUxon.
            foreach ($configurator->getOptionalColumnsUxon()->toArray() as $existingColumn) {
                $expr = $existingColumn['attribute_alias'] ?? $existingColumn['calculation'] ?? null;
                if ($expr !== null) {
                    $expressions[] = $expr;
                }
            }
        }
        return $expressions;
    }

    /**
     * Makes sure a column added to a table, that is aggregated (grouped), either matches one of the
     * grouping attributes or has an aggregator - otherwise the column would not be usable when reading
     * aggregated data (e.g. the resulting SQL query would fail because the column would be neither
     * grouped, nor aggregated).
     *
     * If the column's attribute is not part of the table's aggregations and has no aggregator of its
     * own yet, the attribute's `default_aggregate_function` is applied automatically.
     *
     * @param DataTableConfigurator $configurator
     * @param array $column
     * @return array
     */
    protected function applyAggregatorIfNeeded(DataTableConfigurator $configurator, array $column) : array
    {
        // Only attribute-bound columns can be affected - calculated columns, etc. are left untouched.
        $alias = $column['attribute_alias'] ?? null;
        // If the column already has an explicit aggregator (e.g. `ATTR:SUM`), there is nothing to do.
        if ($alias === null || DataAggregation::hasAggregation($alias)) {
            return $column;
        }

        // If the table is not aggregated (grouped) at all, plain columns work just fine as they are.
        $table = $configurator->getWidgetConfigured();
        if (! $table->hasAggregations()) {
            return $column;
        }

        // If the attribute cannot be resolved (e.g. invalid alias), just leave the column as-is -
        // this is not the right place to raise an error about that.
        try {
            $attr = $table->getMetaObject()->getAttribute($alias);
        } catch (MetaAttributeNotFoundError $e) {
            return $column;
        }

        // If the attribute is one of the attributes the table is grouped by, it can be used without
        // an aggregator - it is already unique per group.
        if ($table->hasAggregationOverAttribute($attr)) {
            return $column;
        }

        // Otherwise the attribute is neither grouped, nor aggregated, which would normally result in
        // an invalid query (e.g. "column must appear in the GROUP BY clause or be used in an aggregate
        // function" in SQL). Automatically apply the attribute's own default aggregator in this case,
        // so the column keeps working inside aggregated tables. If there is no default aggregator
        // defined for the attribute, leave the column untouched, since there is no safe way to guess
        // which aggregator to use.
        if ($defaultAggregator = $attr->getDefaultAggregateFunction()) {
            $column['attribute_alias'] = DataAggregation::addAggregatorToAlias($alias, $defaultAggregator);
        }

        return $column;
    }

    /**
     * 
     * @param \exface\Core\Events\Widget\OnUiActionWidgetInitEvent $event
     * @return void
     */
    public function onUiActionWidgetInitialized(OnUiActionWidgetInitEvent $event) : void
    {
        if ($this->buttonsToAddUxon === null && $this->sideBarToAdd === null && $this->propertiesToSet === null) {
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
        
        if ($this->propertiesToSet !== null) {
            $widget->importUxonObject($this->propertiesToSet);
        }
        
        if ($this->buttonsToAddUxon !== null && $widget instanceof iHaveButtons) {
            $this->addButtonsToWidget($widget, $this->buttonsToAddUxon);
        }

        if ($this->sideBarToAdd !== null && $widget instanceof iHaveSidebar) {
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
     * @uxon-type \exface\Core\Widgets\Sidebar
     * @uxon-template {"widgets": [{"widget_type": ""}]}
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
        foreach ($aliasOrUids->toArray() as $alias) {
            if (RegularExpressionDataType::isRegex($alias)) {
                $this->onlyOnPagesRegex[] = $alias;
            } else {
                $this->onlyOnPages[] = $alias;
            }
        }
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

    /**
     * Set direct properties of whatever widget the behavior is modifying - e.g. width, caption, etc.
     * 
     * @uxon-property change_properties
     * @uxon-type \exface\Core\Widgets\AbstractWidget
     * @uxon-template {"": ""}
     * 
     * @param UxonObject $uxon
     * @return $this
     */
    protected function setChangeProperties(UxonObject $uxon) : WidgetModifyingBehavior
    {
        $this->propertiesToSet = $uxon;
        return $this;
    }
}