<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iHaveFilters;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Interfaces\Widgets\iHaveColumnGroups;
use exface\Core\Factories\DataColumnTotalsFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Widgets\iHaveContextualHelp;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
use exface\Core\Interfaces\Widgets\iConfigureWidgets;
use exface\Core\Interfaces\Widgets\iHaveHeader;
use exface\Core\Interfaces\Widgets\iHaveFooter;
use exface\Core\Widgets\Traits\iSupportLazyLoadingTrait;
use exface\Core\Exceptions\Widgets\WidgetPropertyNotSetError;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\Widgets\iCanPreloadData;
use exface\Core\Widgets\Traits\iCanPreloadDataTrait;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\Widgets\iTakeInput;

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
        iSupportLazyLoading, 
        iHaveContextualHelp, 
        iHaveConfigurator, 
        iShowData,
        iCanPreloadData
{
    use iHaveButtonsAndToolbarsTrait {
        prepareDataSheetToPreload as prepareDataSheetToPreloadViaTrait;
        setButtons as setButtonsViaTrait;
    }
    use iCanPreloadDataTrait;
    use iSupportLazyLoadingTrait {
        setLazyLoading as setLazyLoadingViaTrait;
        getLazyLoadingActionAlias as getLazyLoadingActionAliasViaTrait;
    }

    // properties
    private $paginate = true;

    private $paginate_page_size = null;
    
    private $paginator = null;

    private $aggregate_by_attribute_alias = null;

    /** @var DataColumnGroup[] */
    private $column_groups = array();
    
    /** @var DataToolbar[] */
    private $toolbars = array();

    // other stuff
    /** @var UxonObject[] */
    private $sorters = array();

    /** @var boolean */
    private $is_editable = false;

    /** @var WidgetLinkInterface */
    private $refresh_with_widget = null;

    private $values_data_sheet = null;

    /**
     * @uxon empty_text The text to be displayed, if there are no data records
     *
     * @var string
     */
    private $empty_text = null;

    private $help_button = null;

    private $hide_help_button = false;
    
    private $configurator = null;
    
    private $hide_refresh_button = null;

    private $hide_header = false;
    
    private $hide_footer = false;
    
    private $has_system_columns = false;

    private $autoload_data = true;
    
    private $autoload_disabled_hint = null;
    
    private $quickSearchWidget = null;
    
    private $quickSearchEnabled = null;

    protected function init()
    {
        parent::init();
        // Add the main column group
        if (count($this->getColumnGroups()) == 0) {
            $this->addColumnGroup($this->getPage()->createWidget('DataColumnGroup', $this));
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::addColumn()
     */
    public function addColumn(DataColumn $column, int $position = NULL) : iHaveColumns
    {
        $this->getColumnGroupMain()->addColumn($column, $position);
        return $this;
    }
    
    /**
     * Creates a DataColumn from a meta attribute.
     * 
     * The column is not automatically added to the column group - use addColumn() explicitly!
     * 
     * For relations the column will automatically show the label of the related object
     *
     * @see iHaveColumns::createColumnFromAttribute()
     */
    public function createColumnFromAttribute(MetaAttributeInterface $attribute, string $caption = null, bool $hidden = null) : DataColumn
    {
        return $this->getColumnGroupMain()->createColumnFromAttribute($attribute, $caption, $hidden);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::createColumnFromUxon()
     */
    public function createColumnFromUxon(UxonObject $uxon) : DataColumn
    {
        return $this->getColumnGroupMain()->createColumnFromUxon($uxon);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::removeColumn()
     */
    public function removeColumn(DataColumn $column) : iHaveColumns
    {
        foreach ($this->getColumnGroups() as $grp) {
            $grp->removeColumn($column);
        }
        return $this;
    }

    /**
     * Returns the id of the column holding the UID of each row.
     * By default it is the column with the UID attribute of
     * the meta object displayed in by the data widget, but this can be changed in the UXON description if required.
     *
     * @return string
     */
    public function getUidColumnId()
    {
        return $this->getColumnGroupMain()->getUidColumnId();
    }

    /**
     * Sets the id of the column to be used as UID for each data row
     *
     * @uxon-property uid_column_id
     * @uxon-type string
     *
     * @param string $value            
     */
    public function setUidColumnId($value)
    {
        $this->getColumnGroupMain()->setUidColumnId($value);
        return $this;
    }

    /**
     * Returns the UID column as DataColumn
     *
     * @return \exface\Core\Widgets\DataColumn
     */
    public function getUidColumn()
    {
        return $this->getColumnGroupMain()->getUidColumn();
    }

    /**
     * Returns TRUE if this data widget has a UID column or FALSE otherwise.
     *
     * @return boolean
     */
    public function hasUidColumn()
    {
        return $this->getColumnGroupMain()->hasUidColumn();
    }

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
                if (! $col->getAttributeAlias())
                    continue;
                $data_column = $data_sheet->getColumns()->addFromExpression($col->getAttributeAlias(), $col->getDataColumnName(), $col->isHidden());
                // Add a total to the data sheet, if the column has a footer
                // TODO wouldn't it be better to use the column id here?
                if ($col->hasFooter()) {
                    $total = DataColumnTotalsFactory::createFromString($data_column, $col->getFooter());
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
                    $data_sheet->addFilterFromString($filter_widget->getAttributeAlias(), $filter_widget->getValue(), $filter_widget->getComparator());
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
                    $fltr = $this->getConfiguratorWidget()->createFilterFromRelation($rel);
                    $data_sheet = $fltr->prepareDataSheetToPrefill($data_sheet);
                }
            }
        }
        return $data_sheet;
    }

    /**
     * Returns an array with all columns of the grid.
     * If no columns have been added yet,
     * default display attributes of the meta object are added as columns automatically.
     *
     * @return DataColumn[]
     */
    public function getColumns() : array
    {
        // If no columns explicitly specified, add the default columns
        if (count($this->getColumnGroups()) == 1 && $this->getColumnGroupMain()->isEmpty()) {
            $this->addColumnsForDefaultDisplayAttributes();
        }
        
        $columns = array();
        if (count($this->getColumnGroups()) == 1) {
            return $this->getColumnGroupMain()->getColumns();
        } else {
            foreach ($this->getColumnGroups() as $group) {
                $columns = array_merge($columns, $group->getColumns());
            }
        }
        
        return $columns;
    }

    /**
     * Returns the number of currently contained columns over all column groups.
     * NOTE: This does not trigger the creation of any default columns!
     *
     * @return int
     */
    public function countColumns() : int
    {
        $count = 0;
        foreach ($this->getColumnGroups() as $group) {
            $count += $group->countColumns();
        }
        return $count;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::hasColumns()
     */
    public function hasColumns() : bool
    {
        foreach ($this->getColumnGroups() as $group){
            if ($group->hasColumns()){
                return true;
            }
        }
        return false;
    }

    /**
     * Creates and adds columns based on the default attributes of the underlying meta object (the ones marked with default_display_order)
     *
     * @return Data
     */
    public function addColumnsForDefaultDisplayAttributes()
    {
        // add the default columns
        $def_attrs = $this->getMetaObject()->getAttributes()->getDefaultDisplayList();
        foreach ($def_attrs as $attr) {
            $alias = ($attr->getRelationPath()->toString() ? $attr->getRelationPath()->toString() . RelationPath::getRelationSeparator() : '') . $attr->getAlias();
            $attr = $this->getMetaObject()->getAttribute($alias);
            $this->addColumn($this->createColumnFromAttribute($attr, null, $attr->isHidden()));
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::getColumn()
     */
    public function getColumn(string $widgetId) : ?DataColumn
    {
        foreach ($this->getColumns() as $col) {
            if ($col->getId() === $widgetId) {
                return $col;
            }
        }
        return null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::getColumnByAttributeAlias()
     */
    public function getColumnByAttributeAlias(string $alias_with_relation_path) : ?DataColumn
    {
        foreach ($this->getColumns() as $col) {
            if ($col->getAttributeAlias() === $alias_with_relation_path) {
                return $col;
            }
        }
        return null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::getColumnByDataColumnName()
     */
    public function getColumnByDataColumnName(string $data_sheet_column_name) : ?DataColumn
    {
        foreach ($this->getColumns() as $col) {
            if ($col->getDataColumnName() === $data_sheet_column_name) {
                return $col;
            }
        }
        return null;
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
     * Defines the columns of data: each element of the array can be a DataColumn or a DataColumnGroup widget.
     *
     * To create a column showing an attribute of the Data's meta object, it is sufficient to only set
     * the attribute_alias for each column object. Other properties like caption, align, editor, etc.
     * are optional. If not set, they will be determined from the properties of the attribute.
     *
     * The widget type (DataColumn or DataColumnGroup) can be omitted: it can be determined automatically:
     * E.g. adding {"attribute_group_alias": "~VISIBLE"} as a column is enough to generate a column group
     * with all visible attributes of the object.
     *
     * Column groups with captions will produce grouped columns with mutual headings (s. example below).
     *
     * Example:
     * "columns": [
     *  {
     *      "attribute_alias": "PRODUCT__LABEL",
     *      "caption": "Product"
     *  },
     *  {
     *      "attribute_alias": "PRODUCT__BRAND__LABEL"
     *  },
     *  {
     *      "caption": "Sales",
     *      "columns": [
     *  {
     *      "attribute_alias": "QUANTITY:SUM",
     *      "caption": "Qty."
     *  },
     *  {
     *      "attribute_alias": "VALUE:SUM",
     *      "caption": "Sum"
     *  }
     * ]
     *
     * @uxon-property columns
     * @uxon-type \exface\Core\Widgets\DataColumn[]|\exface\Core\Widgets\DataColumnGroup[]
     * @uxon-template [{"attribute_alias": ""}]
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::setColumns()
     */
    public function setColumns(UxonObject $columns) : iHaveColumns
    {
        $column_groups = array();
        $last_element_was_a_column_group = false;
        
        /*
         * The columns array of a data widget can contain columns or column groups or a mixture of those.
         * At this point, we must sort them apart
         * and make sure, all columns get wrappen in groups. Directly specified columns will get a generated
         * group, which won't have anything but the column list. If we have a user specified column group
         * somewhere in the middle, there will be two generated groups left and right of it. This makes sure,
         * that the get_columns() method, which lists all columns from all groups will list them in exact the
         * same order as the user had specified!
         */
        
        // Loop through all uxon elements in the columns array and separate columns and column groups
        // This is nesseccary because column groups can be created in short notation (just like a regular
        // column with a nested column list and an optional caption).
        // Additionally we will make sure, that all columns are within column groups, so we can jus instatiate
        // the groups, not each column separately. The actual instantiation of the corresponding widgets will
        // follow in the next step.
        foreach ($columns as $c) {
            if ($c instanceof UxonObject) {
                if ($c->isArray()) {
                    // If the element is an array itself (nested in columns), it is a column group
                    $column_groups[] = $c;
                    $last_element_was_a_column_group = true;
                } elseif (strcasecmp($c->getProperty('widget_type'), 'DataColumnGroup') === 0 || $c->hasProperty('columns')) {
                    // If not, check to see if it's widget type is DataColumnGroup or it has an array of columns itself
                    // If so, it still is a column group
                    $column_groups[] = $c;
                    $last_element_was_a_column_group = true;
                } else {
                    // If none of the above applies, it is a regular column, so we need to put it into a column group
                    // We start a new group, if the last element added was a columnt group or append it to the last
                    // group if that was built from single columns already
                    if (! count($column_groups) || $last_element_was_a_column_group) {
                        $group = new UxonObject();
                        $column_groups[] = $group;
                    } else {
                        $group = $column_groups[(count($column_groups) - 1)];
                    }
                    $group->appendToProperty('columns', $c);
                    $last_element_was_a_column_group = false;
                }
            } else {
                throw new WidgetPropertyInvalidValueError($this, 'The elements of "columns" in a data widget must be objects or arrays, "' . gettype($c) . '" given instead!', '6T91RQ5');
            }
        }
        
        // Now that we have put all column into groups, we can instatiate these as widgets.
        foreach ($column_groups as $nr => $group) {
            // The first column group is always treated as the main one. So check to see, if there is a main
            // column group already and, if so, simply make it load the uxon description of the first column
            // group.
            if ($nr == 0 && count($this->getColumnGroups()) > 0) {
                $this->getColumnGroupMain()->importUxonObject($group);
            } else {
                $page = $this->getPage();
                $column_group = WidgetFactory::createFromUxon($page, UxonObject::fromAnything($group), $this, 'DataColumnGroup');
                $this->addColumnGroup($column_group);
            }
        }
        return $this;
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

    public function createFilterWidget($attribute_alias = null, UxonObject $uxon_object = null)
    {
        return $this->getConfiguratorWidget()->createFilterWidget($attribute_alias, $uxon_object);
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
            if (count($attribute_filters) == 0) {
                $filter = $this->createFilterWidget($condition->getExpression()->getAttribute()->getAliasWithRelationPath());
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
                $filter_widget = $this->getConfiguratorWidget()->createFilterFromRelation($rel);
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
     * @uxon-template {"count_all_rows": "true"}
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
     * If not set, the template's default value will be used.
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
    public function getSorters()
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
     * TODO use special sorter widgets here instead of plain uxon objects
     * 
     * @param UxonObject $sorters            
     */
    public function setSorters(UxonObject $sorters)
    {
        foreach ($sorters as $uxon){
            $this->addSorter($uxon->getProperty('attribute_alias'), $uxon->getProperty('direction'));
        }
        return $this;
    }
    
    public function addSorter($attribute_alias, $direction)
    {
        $this->getConfiguratorWidget()->addSorter($attribute_alias, $direction);
        // TODO move sorters completely to configuration widget!
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
     * @return array
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
     * Returns an array of aliases of attributes, that should be used for quick search relative to the meta object of the widget
     * 
     * IDEA move to to configurator?
     *
     * @return array
     */
    public function getAttributesForQuickSearch()
    {
        $aliases = array();
        foreach ($this->getConfiguratorWidget()->getQuickSearchFilters() as $fltr) {
            $aliases[] = $fltr->getAttributeAlias();
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
     * Makes data values get loaded asynchronously in background if the template supports it (i.e.
     * via AJAX).
     *
     * @uxon-property lazy_loading
     * @uxon-type boolean
     * 
     * TODO should this option not be set recursively in general - not only for the configurator?
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading($value)
    {
        $result = $this->setLazyLoadingViaTrait($value);
        $this->getConfiguratorWidget()->setLazyLoading($value);
        return $result;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingActionAlias()
     */
    public function getLazyLoadingActionAlias()
    {
        try {
            $result = $this->getLazyLoadingActionAliasViaTrait();
        } catch (WidgetPropertyNotSetError $e) {
            $this->setLazyLoadingActionAlias('exface.Core.ReadData');
            $result = $this->getLazyLoadingActionAliasViaTrait();
        }
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
     * The text may contain any template-specific formatting: e.g. HTML for HTML-templates.
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
     *
     * @return DataColumnGroup
     */
    public function getColumnGroups()
    {
        return $this->column_groups;
    }

    /**
     *
     * @return \exface\Core\Widgets\DataColumnGroup
     */
    public function getColumnGroupMain()
    {
        return $this->getColumnGroups()[0];
    }

    /**
     *
     * @param DataColumnGroup $column_group            
     * @return Data
     */
    public function addColumnGroup(DataColumnGroup $column_group)
    {
        $this->column_groups[] = $column_group;
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

    public function setValuesDataSheet(DataSheetInterface $data_sheet)
    {
        $this->values_data_sheet = $data_sheet;
        return $this;
    }

    public function getHelpButton()
    {
        if (is_null($this->help_button)) {
            $this->help_button = WidgetFactory::create($this->getPage(), $this->getButtonWidgetType(), $this);
            $this->help_button->setActionAlias('exface.Core.ShowHelpDialog');
            $this->help_button->setHidden(true);
        }
        return $this->help_button;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHelpWidget()
     */
    public function getHelpWidget(iContainOtherWidgets $help_container)
    {
        /**
         *
         * @var DataTable $table
         */
        $table = WidgetFactory::create($help_container->getPage(), 'DataTableResponsive', $help_container);
        $object = $this->getWorkbench()->model()->getObject('exface.Core.USER_HELP_ELEMENT');
        $table->setMetaObject($object);
        $table->setCaption($this->getWidgetType() . ($this->getCaption() ? '"' . $this->getCaption() . '"' : ''));
        $table->addColumn($table->createColumnFromAttribute($object->getAttribute('TITLE')));
        $table->addColumn($table->createColumnFromAttribute($object->getAttribute('DESCRIPTION')));
        $table->setLazyLoading(false);
        $table->setPaginate(false);
        $table->setNowrap(false);
        $table->setRowGrouper(UxonObject::fromArray(array(
            'group_by_attribute_alias' => 'GROUP',
            'hide_caption' => true
        )));
        
        // IMPORTANT: make sure the help table does not have a help button itself, because that would result in having
        // infinite children!
        $table->setHideHelpButton(true);
        
        $data_sheet = DataSheetFactory::createFromObject($object);
        
        foreach ($this->getFilters() as $filter) {
            $row = array(
                'TITLE' => $filter->getCaption(),
                'GROUP' => $this->translate('WIDGET.DATA.HELP.FILTERS')
            );
            if ($attr = $filter->getAttribute()) {
                $row = array_merge($row, $this->getHelpRowFromAttribute($attr));
            }
            $data_sheet->addRow($row);
        }
        
        foreach ($this->getColumns() as $col) {
            $row = array(
                'TITLE' => $col->getCaption(),
                'GROUP' => $this->translate('WIDGET.DATA.HELP.COLUMNS')
            );
            if ($attr = $col->getAttribute()) {
                $row = array_merge($row, $this->getHelpRowFromAttribute($attr));
            }
            $data_sheet->addRow($row);
        }
        
        $table->prefill($data_sheet);
        
        $help_container->addWidget($table);
        return $help_container;
    }

    /**
     * Returns a row (assotiative array) for a data sheet with exface.Core.USER_HELP_ELEMENT filled with information about
     * the given attribute.
     * The inforation is derived from the attributes meta model.
     *
     * @param MetaAttributeInterface $attr            
     * @return string[]
     */
    protected function getHelpRowFromAttribute(MetaAttributeInterface $attr)
    {
        $row = array();
        $row['DESCRIPTION'] = $attr->getShortDescription() ? rtrim(trim($attr->getShortDescription()), ".") . '.' : '';
        
        if (! $attr->getRelationPath()->isEmpty()) {
            $row['DESCRIPTION'] .= $attr->getObject()->getShortDescription() ? ' ' . rtrim($attr->getObject()->getShortDescription(), ".") . '.' : '';
        }
        return $row;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHideHelpButton()
     */
    public function getHideHelpButton()
    {
        return $this->hide_help_button;
    }

    /**
     * Set to TRUE to remove the contextual help button.
     * Default: FALSE.
     *
     * @uxon-property hide_help_button
     * @uxon-type boolean
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::setHideHelpButton()
     */
    public function setHideHelpButton($value)
    {
        $this->hide_help_button = BooleanDataType::cast($value);
        return $this;
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
        $uxon->setProperty('lazy_loading_action', $this->getLazyLoadingActionAlias());
        $uxon->setProperty('lazy_loading_group_id', $this->getLazyLoadingGroupId());
        
        foreach ($this->getColumnGroups() as $col_group) {
            $uxon->appendToProperty('columns', $col_group->exportUxonObject());
        }
        
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
        
        return $uxon;
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
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidget()
     * @return DataConfigurator
     */
    public function getConfiguratorWidget() : iConfigureWidgets
    {
        if ($this->configurator === null) {
            $this->configurator = WidgetFactory::create($this->getPage(), $this->getConfiguratorWidgetType(), $this);
        }
        return $this->configurator;
    }
    
    public function setConfigurator(UxonObject $uxon) : iHaveConfigurator
    {
        if ($this->configurator === null) {
            $this->configurator = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, $this->getConfiguratorWidgetType());
            $this->configurator->setWidgetConfigured($this);
        } else {
            $this->configurator->importUxonObject($uxon);
        }
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::setConfiguratorWidget()
     */
    public function setConfiguratorWidget(iConfigureWidgets $widget) : iHaveConfigurator
    {
        $this->configurator = $widget->setWidgetConfigured($this);
        return $this;
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
    
    public function getHideHeader()
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
    public function setHideHeader($value)
    {
        $this->hide_header = \exface\Core\DataTypes\BooleanDataType::cast($value);
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
     * Overrides the text shown if autoload_data is set to FALSE.
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
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::getColumnDefaultWidgetType()
     */
    public function getColumnDefaultWidgetType() : string
    {
        return 'DataColumn';
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
    
    public function getQuickSearchPlaceholder()
    {
        $quick_search_fields = $this->getMetaObject()->getLabelAttribute() ? $this->getMetaObject()->getLabelAttribute()->getName() : '';
        foreach ($this->getConfiguratorWidget()->getQuickSearchFilters() as $qfltr) {
            $quick_search_fields .= ($quick_search_fields ? ', ' : '') . $qfltr->getCaption();
        }
        
        return $quick_search_fields;
    }
    
    /**
     *
     * @return bool
     */
    public function getQuickSearchEnabled() : bool
    {
        return $this->quickSearchEnabled;
    }
    
    /**
     * Set to TRUE/FALSE to enable or disable quick search functionality.
     * 
     * By default, the templates are free to decide, if quick search should be used
     * for specific data widgets.
     * 
     * @uxon-property quick_search_enabled
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return Data
     */
    public function setQuickSearchEnabled(bool $value) : Data
    {
        $this->quickSearchEnabled = $value;
        return $this;
    }
    
    /**
     *
     * @return Input
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
     * @param UxonObject $value
     * @return Data
     */
    public function setQuickSearchWidget(UxonObject $uxon) : Data
    {
        $this->quickSearchWidget = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'Input');
        return $this;
    }
}