<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iHaveFilters;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Interfaces\Widgets\iHaveColumnGroups;
use exface\Core\Factories\DataColumnTotalsFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Widgets\iHaveContextualHelp;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
use exface\Core\Interfaces\Widgets\iHaveHeader;
use exface\Core\Interfaces\Widgets\iHaveFooter;
use exface\Core\Widgets\Traits\iSupportLazyLoadingTrait;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\Widgets\iCanPreloadData;
use exface\Core\Widgets\Traits\iCanPreloadDataTrait;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\Widgets\iHaveQuickSearch;
use exface\Core\Widgets\Traits\iHaveContextualHelpTrait;
use exface\Core\Widgets\Traits\iHaveColumnsAndColumnGroupsTrait;
use exface\Core\Widgets\Traits\iHaveConfiguratorTrait;
use exface\Core\Interfaces\Widgets\iHaveSorters;
use exface\Core\Widgets\Parts\DataFooter;

/**
 * Data is the base for all widgets displaying tabular data.
 *
 * Many widgets like Chart, InputComboTable, etc. contain internal Data sub-widgets, that define the data set used
 * by these widgets. Datas are much like tables: you can define columns, sorters, filters, pagination rules, etc.
 * 
 * @method DataButton[] getButtons(callable $filter_callback = null)
 * @method DataToolbar[] getToolbars()
 * @method DataToolbar getToolbarMain()
 *
 * @author Andrej Kabachnik
 *        
 */
class Data 
    extends AbstractWidget 
    implements 
        iHaveHeader, 
        iHaveFooter, 
        iHaveColumns, 
        iHaveColumnGroups, 
        iHaveToolbars, 
        iHaveButtons, 
        iHaveFilters, 
        iHaveSorters,
        iHaveQuickSearch,
        iSupportLazyLoading, 
        iHaveContextualHelp, 
        iHaveConfigurator, 
        iShowData,
        iCanPreloadData
{
    use iHaveColumnsAndColumnGroupsTrait;
    use iHaveButtonsAndToolbarsTrait {
        prepareDataSheetToPreload as prepareDataSheetToPreloadViaTrait;
        setButtons as setButtonsViaTrait;
    }
    use iCanPreloadDataTrait;
    use iSupportLazyLoadingTrait {
        setLazyLoading as setLazyLoadingViaTrait;
        getLazyLoadingActionAlias as getLazyLoadingActionAliasViaTrait;
    }
    use iHaveContextualHelpTrait;
    use iHaveConfiguratorTrait;

    // properties
    private $paginate = true;

    private $paginate_page_size = null;
    
    private $paginator = null;

    private $aggregate_by_attribute_alias = null;
    
    private $aggregate_all = null;

    /** @var DataToolbar[] */
    private $toolbars = array();

    // other stuff
    /** @var UxonObject[] */
    private $sorters = array();

    /** @var boolean */
    private $is_editable = false;
    
    private $editable_changes_reset_on_refresh = true;

    /** @var WidgetLinkInterface */
    private $refresh_with_widget = null;

    private $values_data_sheet = null;

    /**
     * @uxon empty_text The text to be displayed, if there are no data records
     *
     * @var string
     */
    private $empty_text = null;

    private $configurator = null;
    
    private $hide_refresh_button = null;

    private $hide_header = null;
    
    private $hide_footer = false;
    
    private $has_system_columns = false;

    private $autoload_data = true;
    
    private $autoload_disabled_hint = null;
    
    private $quickSearchWidget = null;
    
    private $quickSearchEnabled = null;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        
        // Columns & Totals
        if ($data_sheet->getMetaObject()->is($this->getMetaObject())) {
            foreach ($this->getColumns() as $col) {
                // Only add columns, that actually have content. The other columns exist only in the widget
                // TODO This check will get more complicated, once the content can be specified not only via attribute_alias
                // but also with properties like formula, etc.
                if (! $col->getAttributeAlias()) {
                    continue;
                }
                $data_column = $data_sheet->getColumns()->addFromExpression($col->getAttributeAlias(), $col->getDataColumnName(), $col->isHidden());
                // Add a total to the data sheet, if the column has a footer
                if ($col->hasFooter() === true && $col->getFooter()->hasAggregator() === true) {
                    $total = DataColumnTotalsFactory::createFromString($data_column, $col->getFooter()->getAggregator()->exportString());
                    $data_column->getTotals()->add($total);
                }
            }
        }
        
        // Aggregations
        foreach ($this->getAggregations() as $attr) {
            $data_sheet->getAggregations()->addFromString($attr);
        }
        
        // Pagination
        if ($this->getPaginator()->getPageSize()) {
            $data_sheet->setRowsLimit($this->getPaginator()->getPageSize());
        }
        if ($this->getPaginator()->getCountAllRows() === false) {
            $data_sheet->setAutoCount(false);
        }
        
        // Filters and sorters only if lazy loading is disabled!
        if (! $this->getLazyLoading()) {
            // Add filters if they have values
            foreach ($this->getFilters() as $filter_widget) {
                if ($filter_widget->getValue()) {
                    $data_sheet->getFilters()->addConditionFromString($filter_widget->getAttributeAlias(), $filter_widget->getValue(), $filter_widget->getComparator());
                }
            }
            // Add sorters
            foreach ($this->getSorters() as $sorter_obj) {
                $data_sheet->getSorters()->addFromString($sorter_obj->getProperty('attribute_alias'), $sorter_obj->getProperty('direction'));
            }
        }
        
        return $data_sheet;
    }

    /**
     *
     * {@inheritdoc} To prefill a dataSet we need to filter it's results, so that they are related to the object we prefill
     *               with. Thus, the prefill data needs to contain the UID of that object.
     *              
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        $prefillObject = $data_sheet->getMetaObject();
        $thisObject = $this->getMetaObject();
        if ($prefillObject->isExactly($thisObject)) {
            if ($thisObject->hasUidAttribute()) {
                // If trying to prefill with an instance of the same object and that object has a UID, we actually just need the 
                // uid column in the resulting prefill sheet to be able to refresh it's data. It will probably be there anyway, 
                // but we still add it here (just in case).
                $data_sheet->getColumns()->addFromExpression($thisObject->getUidAttributeAlias());
            } else {
                // TODO If it's the same object, but it does not have a UID, I don't really know, what's best.
                // Currently, the sheet will just be returned as is.
                return $data_sheet;
            }
        } else {
            // If trying to prefill with a different object, we need to find a relation to that object somehow.
            // First we check for filters based on the prefill object. If filters exists, we can be sure, that those
            // are the ones to be prefilled.
            $relevant_filters = $this->getConfiguratorWidget()->findFiltersByObject($prefillObject);
            $uid_filters_found = false;
            // If there are filters over UIDs of the prefill object, just get data for these filters for the prefill,
            // because it does not make sense to fetch prefill data for UID-filters and attribute filters at the same
            // time. If data for the other filters will be found in the prefill sheet when actually doing the prefilling,
            // it should, of course, be applied too, but we do not tell ExFace to always fetch this data.
            foreach ($relevant_filters as $fltr) {
                if ($fltr->getAttribute()->isRelation() && $fltr->getAttribute()->getRelation()->getRightObject()->isExactly($prefillObject)) {
                    $data_sheet = $fltr->prepareDataSheetToPrefill($data_sheet);
                    $uid_filters_found = true;
                }
            }
            // If thre are no UID-filters, than we can request data for the other filters.
            if (count($relevant_filters) > 0 && ! $uid_filters_found) {
                foreach ($relevant_filters as $fltr) {
                    $data_sheet = $fltr->prepareDataSheetToPrefill($data_sheet);
                }
            }
            
            // If there is no filter defined explicitly, try to find a relation and create a corresponding filter
            if (! $fltr) {
                // TODO currently this only works for direct relations, not for chained ones.
                // FIXME check, if a filter on the current relation is there already, and add it only in this case
                /* @var $rel \exface\Core\CommonLogic\Model\relation */
                if ($rel = $thisObject->findRelation($prefillObject)) {
                    $fltr = $this->getConfiguratorWidget()->addFilterFromRelation($rel);
                    $data_sheet = $fltr->prepareDataSheetToPrefill($data_sheet);
                }
            }
        }
        return $data_sheet;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see iHaveColumnsAndColumnGroupsTrait::createDefaultColumns()
     */
    public function createDefaultColumns() : array
    {
        // add the default columns
        $def_attrs = $this->getMetaObject()->getAttributes()->getDefaultDisplayList();
        $cols = [];
        foreach ($def_attrs as $attr) {
            $alias = ($attr->getRelationPath()->toString() ? $attr->getRelationPath()->toString() . RelationPath::getRelationSeparator() : '') . $attr->getAlias();
            $attr = $this->getMetaObject()->getAttribute($alias);
            $cols[] = $this->createColumnFromAttribute($attr);
        }
        return $cols;
    }
    
    /**
     * Returns an array with columns containing system attributes
     *
     * @return \exface\Core\Widgets\DataColumn[]
     */
    public function getColumnsWithSystemAttributes() : array
    {
        $result = array();
        foreach ($this->getColumns() as $col) {
            if ($col->isBoundToAttribute() && $col->getAttribute()->isSystem()) {
                $result[] = $col;
            }
        }
        return $result;
    }

    /**
     * Returns an array of button widgets, that are explicitly bound to a double click on a data element
     *
     * @param string $mouse_action            
     * @return DataButton[]
     */
    public function getButtonsBoundToMouseAction($mouse_action)
    {
        $result = array();
        foreach ($this->getButtons() as $btn) {
            if ($btn instanceof DataButton && $btn->getBindToMouseAction() == $mouse_action) {
                $result[] = $btn;
            }
        }
        return $result;
    }

    /**
     * Returns an array with all filter widgets.
     *
     * @return Filter[]
     */
    public function getFilters()
    {
        if (! $this->getConfiguratorWidget()->hasFilters()) {
            $this->addRequiredFilters();
        }
        return $this->getConfiguratorWidget()->getFilters();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::getFilter()
     */
    public function getFilter($filter_widget_id)
    {
        return $this->getConfiguratorWidget()->getFilter($filter_widget_id);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::getFiltersApplied()
     */
    public function getFiltersApplied()
    {
        return $this->getConfiguratorWidget()->getFiltersApplied();
    }

    /**
     * Defines filters to be used in this data widget: each being a Filter widget.
     *
     * The simples filter only needs to contain an attribute_alias. ExFace will generate a suitable widget
     * automatically. However, the filter can easily be customized by adding any properties applicable to
     * the respective widget type. You can also override the widget type.
     *
     * Relations and aggregations are fully supported by filters
     *
     * Note, that InputComboTable widgets will be automatically generated for related objects if the corresponding
     * filter is defined by the attribute, representing the relation: e.g. for a table of ORDER_POSITIONS,
     * adding the filter ORDER (relation to the order) will give you a InputComboTable, while the filter ORDER__NUMBER
     * will yield a numeric input field, because it filter over a number, even thoug a related one.
     *
     * Advanced users can also instantiate a Filter widget manually (widget_type = Filter) gaining control
     * over comparators, etc. The widget displayed can then be defined in the widget-property of the Filter.
     *
     * A good way to start is to copy the columns array and rename it to filters. This will give you filters
     * for all columns.
     *
     * Example:
     * 
     * ```
     *  {
     *      "object_alias": "ORDER_POSITION"
     *      "filters": [
     *          {
     *              "attribute_alias": "ORDER"
     *          },
     *          {
     *              "attribute_alias": "CUSTOMER__CLASS"
     *          },
     *          {
     *              "attribute_alias": "ORDER__ORDER_POSITION__VALUE:SUM",
     *              "caption": "Order total"
     *          },
     *          {
     *              "attribute_alias": "VALUE",
     *              "widget_type": "InputNumberSlider"
     *          }
     *      ]
     *  }
     * 
     * ```
     *
     * @uxon-property filters
     * @uxon-type \exface\Core\Widgets\Filter[]
     * @uxon-template [{"attribute_alias": ""}]
     *
     * @param UxonObject $uxon_objects
     * @return Data
     */
    public function setFilters(UxonObject $uxon_objects)
    {
        $this->getConfiguratorWidget()->setFilters($uxon_objects);
        $this->addRequiredFilters();
        return $this;
    }

    /**
     *
     * @see \exface\Core\Widgets\AbstractWidget::prefill()
     */
    protected function doPrefill(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet)
    {
        if ($data_sheet->getMetaObject()->isExactly($this->getMetaObject())) {
            return $this->doPrefillWithDataObject($data_sheet);
        } else {
            return $this->doPrefillWithRelatedObject($data_sheet);
        }
    }
    
    protected function doPrefillWithDataObject(DataSheetInterface $data_sheet)
    {
        // TODO #OnPrefillChangePropertyEvent
        // What data pointer do we pass to the event??? Perhaps we need different pointer types:
        // DataPointerColumn, DataPointerFilter, DataPointerRange, etc.?
        
        // If the prefill data is based on the same object as the widget, inherit the filter conditions from the prefill
        foreach ($data_sheet->getFilters()->getConditions() as $condition) {
            // For each filter condition look for filters over the same attribute.
            // Skip conditions not based on attributes.
            if (! $condition->getExpression()->isMetaAttribute()) {
                continue;
            }
            $attr = $condition->getExpression()->getAttribute();
            $attribute_filters = $this->getConfiguratorWidget()->findFiltersByAttribute($attr);
            // If no filters are there, create one
            if (empty($attribute_filters) === true) {
                $filter = $this->getConfiguratorWidget()->createFilterWidget($condition->getExpression()->getAttribute()->getAliasWithRelationPath());
                $this->addFilter($filter);
                $filter->setValue($condition->getValue());
                // Disable the filter because if the user changes it, the
                // prefill will not be consistent anymore (some prefilled
                // widgets may have different prefill-filters than others)
                    $filter->setDisabled(true);
            } else {
                // If matching filters were found, prefill them
                $prefilled = false;
                foreach ($attribute_filters as $filter) {
                    if ($filter->getComparator() == $condition->getComparator()) {
                        if ($filter->isPrefillable()) {
                            $filter->setValue($condition->getValue());
                        }
                        $prefilled = true;
                    }
                }
                if ($prefilled == false) {
                    $filter = $attribute_filters[0];
                    if ($filter->isPrefillable()) {
                        $filter->setValue($condition->getValue());
                    }
                }
            }
        }
        // If the data should not be loaded layzily, and the prefill has data, use it as value
        if (! $this->getLazyLoading() && ! $data_sheet->isEmpty()) {
            $this->setValuesDataSheet($data_sheet);
        }
        return;
    }
    
    protected function doPrefillWithRelatedObject(DataSheetInterface $data_sheet)
    {
        // if the prefill contains data for another object, than this data set contains, see if we try to find a relation to
        // the prefill-object. If so, show only data related to the prefill (= add the prefill object as a filter)
        
        // First look if the user already specified a filter with the object we are looking for
        foreach ($this->getConfiguratorWidget()->findFiltersByObject($data_sheet->getMetaObject()) as $fltr) {
            $fltr->prefill($data_sheet);
        }
        
        // Otherwise, try to find a suitable relation via generic relation searcher
        // TODO currently this only works for direct relations, not for chained ones.
        if (! $fltr && $rel = $this->getMetaObject()->findRelation($data_sheet->getMetaObject())) {
            // If anything goes wrong, log away the error but continue, as
            // the prefills are not critical in general.
            try {
                $filter_widget = $this->getConfiguratorWidget()->addFilterFromRelation($rel);
                $filter_widget->prefill($data_sheet);
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e);
            }
        }
        
        // Apart from trying to prefill a filter, we should also look if we can reuse filters from the given prefill sheet.
        // This is the case, if this data widget has a filter over exactly the same attribute, as the prefill sheet.
        if (! $data_sheet->getFilters()->isEmpty()) {
            foreach ($data_sheet->getFilters()->getConditions() as $condition) {
                // Skip conditions without attributes or with broken expressions (we do not want errors from the prefill logic!)
                if (! $condition->getExpression()->isMetaAttribute() || ! $condition->getExpression()->getAttribute())
                    continue;
                    // See if there are filters in this widget, that work on the very same attribute
                    foreach ($this->getConfiguratorWidget()->findFiltersByObject($condition->getExpression()->getAttribute()->getObject()) as $fltr) {
                        if ($fltr->getAttribute()->getObject()->is($condition->getExpression()->getAttribute()->getObject()) && $fltr->getAttribute()->getAlias() == $condition->getExpression()->getAttribute()->getAlias() && ! $fltr->getValue()) {
                            $fltr->setComparator($condition->getComparator());
                            $fltr->setValue($condition->getValue());
                            // TODO #OnPrefillChangePropertyEvent - same problem as in doPrefillWithDataObject()
                        }
                    }
            }
        }
        return;
    }

    /**
     * Adds a widget as a filter.
     * Any widget, that can be used to input a value, can be used for filtering. It will automatically be wrapped in a filter
     * widget. The second parameter (if set to TRUE) will make the filter automatically get used in quick search queries.
     *
     * @param AbstractWidget $filter_widget            
     * @param boolean $include_in_quick_search            
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::addFilter()
     */
    public function addFilter(AbstractWidget $filter_widget, $include_in_quick_search = false)
    {
        $this->getConfiguratorWidget()->addFilter($filter_widget, $include_in_quick_search);
        return $this;
    }

    protected function addRequiredFilters()
    {
        // Check for required filters
        foreach ($this->getMetaObject()->getDataAddressRequiredPlaceholders(false, true) as $ph) {
            // If the placeholder is an attribute, add a required filter on it (or make an existing filter required)
            if ($ph_attr = $this->getMetaObject()->getAttribute($ph)) {
                if ($this->getConfiguratorWidget()->hasFilters()) {
                    $ph_filters = $this->getConfiguratorWidget()->findFiltersByAttribute($ph_attr);
                    foreach ($ph_filters as $ph_filter) {
                        $ph_filter->setRequired(true);
                    }
                } else {
                    $ph_filter = $this->getConfiguratorWidget()->createFilterWidget($ph);
                    $ph_filter->setRequired(true);
                    $this->addFilter($ph_filter);
                }
            }
        }
        return $this;
    }

    public function hasFilters()
    {
        if (! $this->getConfiguratorWidget()->hasFilters()) {
            $this->addRequiredFilters();
        }
        return $this->getConfiguratorWidget()->hasFilters();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren() : \Iterator
    {
        yield $this->getConfiguratorWidget();
        
        foreach ($this->getToolbars() as $tb) {
            yield $tb;
        }
        
        // IDEA yield column groups? They are actually the direct children...
        foreach ($this->getColumns() as $col) {
            yield $col;
        }
        
        // Add the help button, so pages will be able to find it when dealing with the ShowHelpDialog action.
        // IMPORTANT: Add the help button to the children only if it is not hidden. This is needed to hide the button in
        // help widgets themselves, because otherwise they would produce their own help widgets, with - in turn - even
        // more help widgets, resulting in an infinite loop.
        if (! $this->getHideHelpButton()) {
            yield $this->getHelpButton();
        }
    }

    /**
     * 
     * @return bool
     */
    public function isPaged() : bool
    {
        return $this->paginate;
    }
    
    /**
     * 
     * @return DataPaginator
     */
    public function getPaginator() : DataPaginator
    {
        if ($this->paginator === null) {
            $this->paginator = WidgetFactory::create($this->getPage(), 'DataPaginator', $this);
        }
        return $this->paginator;
    }
    
    /**
     * Overrides pagination behavior by defining a custom paginator widget.
     * 
     * If a paginator is defined, the property `paginate` will automatically be set to `true`.
     * 
     * Example:
     * 
     * ```
     * {
     *  "widget_type": "DataTable",
     *  "paginator": {
     *      "page_size": 40,
     *      "page_sizes": [20, 40, 100, 200]
     *  }
     * }
     * 
     * ```
     * 
     * @uxon-property paginator
     * @uxon-type \exface\Core\Widgets\DataPaginator
     * @uxon-template {"count_all_rows": true}
     * 
     * @param UxonObject $uxon
     * @return Data
     */
    public function setPaginator(UxonObject $uxon) : Data
    {
        $this->paginator = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'DataPaginator');
        return $this;
    }
    
    /**
     * @deprecated use getPaginator()->setPageSize() instead!
     * 
     * Sets the number of rows to show on one page (only if pagination is enabled).
     * If not set, the facade's default value will be used.
     *
     * @param integer $value
     * @return \exface\Core\Widgets\Data
     */
    public function setPaginatePageSize($value)
    {
        $this->getPaginator()->setPageSize($value);
        return $this;
    }
    
    /**
     * Set to FALSE to disable pagination
     *
     * @uxon-property paginate
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param boolean $value            
     */
    public function setPaginate($value)
    {
        $this->paginate = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    /**
     * Returns an all data sorters applied to this sheet as an array.
     *
     * @return UxonObject[]
     */
    public function getSorters() : array
    {
        return $this->sorters;
    }

    /**
     * Defines sorters for the data via array of sorter objects.
     *
     * Example:
     * 
     * ´´´
     *  {
     *      "sorters": [
     *          {
     *              "attribute_alias": "MY_ALIAS",
     *              "direction": "ASC"
     *          },
     *          {
     *              ...
     *          }
     *      ]
     *  }
     *  
     * ´´´
     *
     * @uxon-property sorters
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSorter[]
     * @uxon-template [{"attribute_alias": "", "direction": "asc"}]
     * 
     * @param UxonObject $sorters            
     */
    public function setSorters(UxonObject $sorters) : iHaveSorters
    {
        foreach ($sorters as $uxon){
            $this->addSorter($uxon->getProperty('attribute_alias'), $uxon->getProperty('direction'));
        }
        return $this;
    }
    
    public function addSorter(string $attribute_alias, string $direction) : iHaveSorters
    {
        // TODO move sorters completely to configuration widget!
        // $this->getConfiguratorWidget()->addSorter($attribute_alias, $direction);
        $sorter = new UxonObject();
        $sorter->setProperty('attribute_alias', $attribute_alias);
        $sorter->setProperty('direction', $direction);
        $this->sorters[] = $sorter;
        return $this;
    }

    public function getAggregateByAttributeAlias()
    {
        return $this->aggregate_by_attribute_alias;
    }

    /**
     * Makes the data get aggregated by the given attribute (i.e. GROUP BY attribute_alias in SQL).
     *
     * Multiple attiribute_alias can be passed as an UXON array (recommended) or separated by commas.
     *
     * @uxon-property aggregate_by_attribute_alias
     * @uxon-type metamodel:attribute[]|string
     * @uxon-template [""]
     *
     * @param string|UxonObject $value            
     * @return Data
     */
    public function setAggregateByAttributeAlias($value)
    {
        if ($value instanceof UxonObject) {
            $this->aggregate_by_attribute_alias = implode(',', $value->toArray());
        } else {
            $this->aggregate_by_attribute_alias = str_replace(', ', ',', $value);
        }
        return $this;
    }

    /**
     * Returns aliases of attributes used to aggregate data
     *
     * @return string[]
     */
    public function getAggregations()
    {
        if ($this->getAggregateByAttributeAlias()) {
            return explode(',', $this->getAggregateByAttributeAlias());
        } else {
            return array();
        }
    }
    
    /**
     * Returns TRUE if the data is aggregated and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasAggregations()
    {
        return empty($this->getAggregations()) === FALSE;
    }
    
    /**
     *
     * @return bool
     */
    public function hasAggregateAll() : bool
    {
        if ($this->aggregate_all === null) {
            if ($this->hasAggregations() === true) {
                return false;
            } 
            
            foreach ($this->getColumns() as $col) {
                if ($col->hasAggregator() === false) {
                    return false;
                }
            }
            return true;
        }
        return $this->aggregate_all;
    }
    
    /**
     * Set to TRUE to aggregate all columns to a single line.
     * 
     * If a column does not have an aggregator, the default aggregator of the attribute
     * will be used.
     * 
     * @uxon-property aggregate_all
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return Data
     */
    public function setAggregateAll(bool $value) : Data
    {
        $this->aggregate_all = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveQuickSearch::getAttributesForQuickSearch()
     */
    public function getAttributesForQuickSearch() : array
    {
        $aliases = array();
        foreach ($this->getConfiguratorWidget()->getQuickSearchFilters() as $fltr) {
            $aliases[] = $fltr->getAttribute();
        }
        return $aliases;
    }

    /**
     * Returns an array of editor widgets.
     * One for every editable data column.
     *
     * @return AbstractWidget[]
     */
    public function getEditors()
    {
        $editors = array();
        foreach ($this->getColumns() as $col) {
            if ($col->isEditable()) {
                $editors[] = $col->getCellWidget();
            }
        }
        return $editors;
    }

    /**
     * Makes data values get loaded asynchronously in background if the facade supports it (i.e.
     * via AJAX).
     *
     * @uxon-property lazy_loading
     * @uxon-type boolean
     * 
     * TODO should this option not be set recursively in general - not only for the configurator?
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading(bool $value) : iSupportLazyLoading
    {
        $result = $this->setLazyLoadingViaTrait($value);
        $this->getConfiguratorWidget()->setLazyLoading($value);
        return $result;
    }

    /**
     * Returns TRUE if the table has a footer with total values and FALSE otherwise
     *
     * @return boolean
     */
    public function hasColumnFooters()
    {
        foreach ($this->getColumns() as $col) {
            if ($col->hasFooter()) {
                return true;
            }
        }
        return false;
    }

    public function getEmptyText()
    {
        if (! $this->empty_text) {
            $this->empty_text = $this->translate('WIDGET.DATA.NO_DATA_FOUND');
        }
        return $this->empty_text;
    }

    /**
     * Sets a custom text to be displayed in the Data widget, if not data is found.
     *
     * The text may contain any facade-specific formatting: e.g. HTML for HTML-facades.
     *
     * @uxon-property empty_text
     * @uxon-type string|metamodel:formula
     *
     * @param string $value            
     * @return Data
     */
    public function setEmptyText($value)
    {
        $this->empty_text = $this->evaluatePropertyExpression($value);
        return $this;
    }

    /**
     * Adds columns with system attributes of the main object or any related object.
     * This is very usefull for editable tables as
     * system attributes are needed to save the data.
     *
     * @param string $relation_path            
     */
    public function addColumnsForSystemAttributes($relation_path = null)
    {
        $object = $relation_path ? $this->getMetaObject()->getRelatedObject($relation_path) : $this->getMetaObject();
        foreach ($object->getAttributes()->getSystem()->getAll() as $attr) {
            $system_alias = RelationPath::relationPathAdd($relation_path, $attr->getAlias());
            // Add the system attribute only if it is not there already.
            // Counting the columns first allows to add the system column without searching for it. If we would search over
            // empty data widgets, we would automatically trigger the creation of default columns, which is absolute nonsense
            // at this point - especially since add_columns_for_system_attributes() can get called before all column defintions
            // in UXON are processed.
            if (! $this->has_system_columns || ! $this->getColumnByAttributeAlias($system_alias)) {
                $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($system_alias), null, true);
                $this->addColumn($col);
            }
        }
        
        if (is_null($relation_path)){
            $this->has_system_columns = true;
        }
        
        return $this;
    }

    /**
     * Returns TRUE, if the data widget contains at least one editable column or column group.
     *
     * @return boolean
     */
    public function isEditable() : bool
    {
        return $this->is_editable;
    }

    /**
     * Set to TRUE to make the column cells editable.
     * 
     * This makes all columns editable, that are bound to an editable model
     * attribute or have no model binding at all. Editable column cells will 
     * automatically use the default editor widget from the bound model attribute 
     * as `cell_widget`.
     *
     * @uxon-property editable
     * @uxon-type boolean
     * 
     * @see \exface\Core\Interfaces\Widgets\iShowData::setEditable()
     */
    public function setEditable($value = true) : iShowData
    {
        $this->is_editable = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getEditableChangesResetOnRefresh() : bool
    {
        return $this->editable_changes_reset_on_refresh;
    }
    
    /**
     * Set to FALSE to make changes in editable columns survive refreshes.
     * 
     * By default, any changes, that were not saved explicitly, will be lost
     * as soon as the widget is refreshed - that is if a search is performed
     * or the data is sorted, etc. If this `editable_changes_reset_on_refresh`
     * is set to `false`, changes made in editable columns will "survive"
     * refreshes. On the other hand, there will be no possibility to revert
     * them, unless there is a dedicated reset-button (e.g. one with action
     * `exface.Core.ResetWidget`).     * 
     * 
     * @uxon-property editable_changes_reset_on_refresh
     * @uxon-type boolean
     * @uxon-default true 
     * 
     * @param bool $value
     * @return Data
     */
    public function setEditableChangesResetOnRefresh(bool $value) : Data
    {
        $this->editable_changes_reset_on_refresh = $value;
        return $this;
    }

    /**
     *
     * @return \exface\Core\Interfaces\Widgets\WidgetLinkInterface
     */
    public function getRefreshWithWidget()
    {
        return $this->refresh_with_widget;
    }

    /**
     * Makes the Data get refreshed after the value of the linked widget changes.
     * Accepts widget links as strings or objects.
     *
     * @uxon-property refresh_with_widget
     * @uxon-type \exface\Core\CommonLogic\WidgetLink
     *
     * @param WidgetLinkInterface|UxonObject|string $value            
     * @return \exface\Core\Widgets\Data
     */
    public function setRefreshWithWidget($widget_link_or_uxon_or_string)
    {
        if ($widget_link_or_uxon_or_string instanceof WidgetLinkInterface) {
            $this->refresh_with_widget = $widget_link_or_uxon_or_string;
        } else {
            $this->refresh_with_widget = WidgetLinkFactory::createFromWidget($this, $widget_link_or_uxon_or_string);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return 'DataButton';
    }

    public function getValuesDataSheet()
    {
        return $this->values_data_sheet;
    }

    /**
     * Set value data sheet for this widget. Parameter either can be of type DateSheetInterface or UxonObject.
     * 
     * @param DataSheetInterface|UxonObject $data_sheet_or_uxon
     * @return Data
     */
    public function setValuesDataSheet($data_sheet_or_uxon) : Data
    {
        $dataSheet = null;
        if ($data_sheet_or_uxon instanceof UxonObject) {
            $dataSheet = DataSheetFactory::createFromObject($this->getMetaObject());
            $dataSheet->importUxonObject($data_sheet_or_uxon);
        } elseif ($data_sheet_or_uxon instanceof DataSheetInterface) {
            $dataSheet = $data_sheet_or_uxon;
        }
        $this->values_data_sheet = $dataSheet;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHelpWidget()
     */
    public function getHelpWidget(iContainOtherWidgets $help_container) : iContainOtherWidgets
    {
        $table = $this->getHelpTable($help_container);
        $data_sheet = DataSheetFactory::createFromObject($table->getMetaObject());
        
        foreach ($this->getFilters() as $filter) {
            $row = array(
                'TITLE' => $filter->getCaption(),
                'GROUP' => $this->translate('WIDGET.DATA.HELP.FILTERS')
            );
            if ($attr = $filter->getAttribute()) {
                $row = array_merge($row, $this->getHelpDataRowFromAttribute($attr, $filter));
            }
            $data_sheet->addRow($row);
        }
        
        foreach ($this->getColumns() as $col) {
            $row = array(
                'TITLE' => $col->getCaption(),
                'GROUP' => $this->translate('WIDGET.DATA.HELP.COLUMNS')
            );
            if ($attr = $col->getAttribute()) {
                $row = array_merge($row, $this->getHelpDataRowFromAttribute($attr, $col->getCellWidget()));
            }
            $data_sheet->addRow($row);
        }
        
        $table->prefill($data_sheet);
        
        $help_container->addWidget($table);
        return $help_container;
    }

    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        
        if ($this->isPaged() === true) {
            $uxon->setProperty('paginate', $this->isPaged());
            $uxon->setProperty('paginator', $this->getPaginator()->exportUxonObject());
        }
        $uxon->setProperty('aggregate_by_attribute_alias', $this->getAggregateByAttributeAlias());
        $uxon->setProperty('lazy_loading', $this->getLazyLoading());
        $uxon->setProperty('lazy_loading_action', $this->getLazyLoadingActionUxon());
        if ($this->getLazyLoadingGroupId() !== null) {
            $uxon->setProperty('lazy_loading_group_id', $this->getLazyLoadingGroupId());
        }
        
        // TODO for now disabled as columns would be duplicated        
        /*foreach ($this->getColumnGroups() as $col_group) {
            $uxon->appendToProperty('columns', $col_group->exportUxonObject());
        }*/
        
        // TODO export toolbars to UXON instead of buttons. Currently all
        // information about toolbars is lost.
        foreach ($this->getButtons() as $button) {
            $uxon->appendToProperty('buttons', $button->exportUxonObject());
        }
        
        foreach ($this->getFilters() as $filter) {
            $uxon->appendToProperty('filters', $filter->exportUxonObject());
        }
        
        $uxon->setProperty('sorters', $this->getSorters());
        
        if ($this->getRefreshWithWidget()) {
            $uxon->setProperty('refresh_with_widget', $this->getRefreshWithWidget()->exportUxonObject());
        }
        if ($this->getPrefillData() !== null) {
            $uxon->setProperty('values_data_sheet', $this->getValuesDataSheet()->exportUxonObject());
        }
        
        return $uxon;
    }
    
    public function setImportValuesDataSheet(UxonObject $uxon)
    {
        $dataSheet = DataSheetFactory::createFromObject($this->getMetaObject());
        $dataSheet->importUxonObject($uxon);
        $this->setValuesDataSheet($dataSheet);
        return $this;
    }
    
    /**
     * The generic Data widget has a simple toolbar, that should merely be a 
     * container for potential buttons. This makes sure all widgets using data
     * internally (like InputComboTables, Charts, etc.) do not have to create complex
     * toolbars, that get automatically generated for DataTables, etc.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveToolbars::getToolbarWidgetType()
     */
    public function getToolbarWidgetType()
    {
        return 'DataToolbar';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidgetType()
     */
    public function getConfiguratorWidgetType() : string
    {
        return 'DataConfigurator';
    } 
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveHeader::getHideHeader()
     */
    public function getHideHeader() : ?bool
    {
        return $this->hide_header;
    }
    
    /**
     * Set to TRUE to hide the top toolbar or FALSE to show it.
     *
     * @uxon-property hide_header
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveHeader::setHideHeader()
     */
    public function setHideHeader(bool $value) : iHaveHeader
    {
        $this->hide_header = $value;
        return $this;
    }
    
    public function getHideFooter()
    {
        return $this->hide_footer;
    }
    
    /**
     * Set to TRUE to hide the bottom toolbar or FALSE to show it.
     *
     * @uxon-property hide_footer
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveHeader::setHideHeader()
     */
    public function setHideFooter($value)
    {
        $this->hide_footer = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    public function getAutoloadData()
    {
        return $this->autoload_data;
    }

    /**
     * Set to FALSE to prevent initial loading of data or TRUE (default) to enable it.
     * 
     * NOTE: if autoload is disabled, the widget will show a message specified in the
     * `autoload_disabled_hint` property.
     * 
     * @uxon-property autoload_data
     * @uxon-type boolean
     * 
     * @param boolean $autoloadData
     * @return Data
     */
    public function setAutoloadData($autoloadData)
    {
        $this->autoload_data = BooleanDataType::cast($autoloadData);
        return $this;
    }
    
    /**
     * Returns a text which can be displayed if initial loading is prevented.
     * 
     * @return string
     */
    public function getAutoloadDisabledHint()
    {
        if ($this->autoload_disabled_hint === null) {
            return $this->translate('WIDGET.DATA.NOT_LOADED');
        }
        return $this->autoload_disabled_hint;
    }
    
    /**
     * Overrides the text shown if autoload_data is set to FALSE or required filters are missing.
     * 
     * Use `=TRANSLATE()` to make the text translatable.
     * 
     * @uxon-property autoload_disabled_hint
     * @uxon-type string|metamodel:formula
     * 
     * @param string $text
     * @return Data
     */
    public function setAutoloadDisabledHint(string $text) : Data
    {
        $this->autoload_disabled_hint = $this->evaluatePropertyExpression($text);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanPreloadData::prepareDataSheetToPreload()
     */
    public function prepareDataSheetToPreload(DataSheetInterface $dataSheet) : DataSheetInterface
    {
        $dataSheet = $this->prepareDataSheetToPreloadViaTrait($dataSheet);
        foreach ($this->getButtons() as $btn) {
            if (! $btn->hasAction()) {
                continue;
            }
            if (! $btn->getAction() instanceof iShowWidget) {
                continue;
            }
            if ($btn->getAction()->getPrefillWithInputData() === false) {
                continue;
            }
            
            $widget = $btn->getAction()->getWidget();
            if (($widget instanceof iCanPreloadData) && $widget->getMetaObject()->is($this->getMetaObject()) && $widget->isPreloadDataEnabled()) {
                $dataSheet = $widget->prepareDataSheetToPreload($dataSheet);
            }
        }
        
        return $dataSheet;
    }
    
    /**
     * An array of buttons to be placed in the main toolbar of the widget.
     * 
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\DataButton[]
     * @uxon-template [{"action_alias": ""}]
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::setButtons()
     */
    public function setButtons($buttons)
    {
        // This method is just here for it's annotations: otherwise the uxon-type would
        // be Button[] instead of DataButton[]
        return $this->setButtonsViaTrait($buttons);
    }
    
    /**
     * 
     */
    public function getQuickSearchPlaceholder() : string
    {
        $quick_search_fields = $this->getMetaObject()->getLabelAttribute() ? $this->getMetaObject()->getLabelAttribute()->getName() : '';
        foreach ($this->getConfiguratorWidget()->getQuickSearchFilters() as $qfltr) {
            $quick_search_fields .= ($quick_search_fields ? ', ' : '') . $qfltr->getCaption();
        }
        
        return $quick_search_fields;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveQuickSearch::getQuickSearchEnabled()
     */
    public function getQuickSearchEnabled() : ?bool
    {
        if ($this->quickSearchEnabled === null && $this->getMetaObject()->hasLabelAttribute() === false && empty($this->getConfiguratorWidget()->getQuickSearchFilters()) === true) {
            return false;
        }
        return $this->quickSearchEnabled;
    }
    
    /**
     * Set to TRUE/FALSE to enable or disable quick search functionality.
     * 
     * By default, the facades are free to decide, if quick search should be used
     * for specific data widgets.
     * 
     * @uxon-property quick_search_enabled
     * @uxon-type boolean
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveQuickSearch::setQuickSearchEnabled()
     */
    public function setQuickSearchEnabled(bool $value) : iHaveQuickSearch
    {
        $this->quickSearchEnabled = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveQuickSearch::getQuickSearchWidget()
     */
    public function getQuickSearchWidget() : Input
    {
        if ($this->quickSearchWidget === null) {
            $this->quickSearchWidget = WidgetFactory::create($this->getPage(), 'Input', $this);
        }
        return $this->quickSearchWidget;
    }
    
    /**
     * Configure the quick-search widget (e.g. to add autosuggest, etc.).
     * 
     * @uxon-property quick_search_widget
     * @uxon-type \exface\Core\Widgets\Input
     * @uxon-tempalte {"widget_type": ""} 
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveQuickSearch::setQuickSearchWidget()
     */
    public function setQuickSearchWidget(UxonObject $uxon) : iHaveQuickSearch
    {
        $this->quickSearchWidget = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'Input');
        return $this;
    }
    
    /**
     * Returns the PHP class name of the footer widget part to be used.
     *
     * @return string
     */
    public function getFooterWidgetPartClass() : string
    {
        return '\\' . DataFooter::class;
    }
}