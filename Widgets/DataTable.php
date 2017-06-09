<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveTopToolbar;
use exface\Core\Interfaces\Widgets\iHaveBottomToolbar;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Interfaces\Widgets\iLayoutWidgets;
use exface\Core\CommonLogic\Model\Attribute;

/**
 * Renders data as a table with filters, columns, and toolbars.
 * Columns of the DataTable can also be made editable.
 *
 * Example:
 * {
 * "id": "attributes",
 * "widget_type": "DataTable",
 * "object_alias": "exface.Core.ATTRIBUTE",
 * "filters": [
 * {
 * "attribute_alias": "OBJECT"
 * },
 * {
 * "attribute_alias": "OBJECT__DATA_SOURCE"
 * }
 * ],
 * "columns": [
 * {
 * "attribute_alias": "OBJECT__LABEL"
 * },
 * {
 * "attribute_alias": "LABEL"
 * },
 * {
 * "attribute_alias": "ALIAS"
 * },
 * {
 * "attribute_alias": "RELATED_OBJ__LABEL",
 * "caption": "Relation to"
 * }
 * ],
 * "buttons": [
 * {
 * "action_alias": "exface.Core.UpdateData"
 * },
 * {
 * "action_alias": "exface.Core.CreateObjectDialog",
 * "caption": "Neu"
 * },
 * {
 * "action_alias": "exface.Core.EditObjectDialog",
 * "bind_to_double_click": true
 * },
 * {
 * "action_alias": "exface.Core.DeleteObject"
 * }
 * ]
 * }
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTable extends Data implements iHaveTopToolbar, iHaveBottomToolbar, iFillEntireContainer, iSupportMultiSelect, iLayoutWidgets
{

    private $show_filter_row = false;

    private $show_row_numbers = false;

    private $multi_select = false;

    private $multi_select_all_selected = false;

    private $striped = true;

    private $nowrap = true;

    private $auto_row_height = true;

    private $hide_toolbar_top = false;

    private $hide_toolbar_bottom = false;

    private $row_details_container = null;

    private $row_details_action = 'exface.Core.ShowWidget';

    private $row_groups_by_column_id = null;

    private $row_groups_expand = 'all';

    private $row_groups_show_count = true;

    private $context_menu_enabled = true;

    private $header_sort_multiple = false;

    private $number_of_columns = null;

    private $column_stack_on_smartphones = null;

    private $column_stack_on_tablets = null;

    function hasRowDetails()
    {
        if (! $this->row_details_container)
            return false;
        else
            return true;
    }

    function hasRowGroups()
    {
        if ($this->getRowGroupsByColumnId())
            return true;
        else
            return false;
    }

    /**
     * Makes each row have a collapsible detail container with arbitrary widgets.
     *
     * Most templates will render an expand-button in each row, allowing to expand/collapse the detail widget.
     * This only works with interactiv templates (e.g. HTML-templates)
     *
     * The widget type of the details-widget can be omitted. It defaults to Container in this case.
     *
     * Example:
     * {
     * height: nnn
     * widgets: [ ... ]
     * }
     *
     * @uxon-property row_details
     * @uxon-type \exface\Core\Widgets\Container
     *
     * @param
     *            $detail_widget
     * @return boolean
     */
    function setRowDetails(\stdClass $detail_widget)
    {
        $page = $this->getPage();
        if (! $detail_widget->widget_type) {
            $detail_widget->widget_type = 'Container';
        }
        $widget = WidgetFactory::createFromUxon($page, $detail_widget, $this);
        if ($widget instanceof Container) {
            $container = $widget;
        } else {
            $container = $this->getPage()->createWidget('Container', $this);
            $container->addWidget($widget);
        }
        $this->setRowDetailsContainer($container);
    }

    public function getChildren()
    {
        $children = parent::getChildren();
        if ($this->hasRowDetails()) {
            $children[] = $this->getRowDetailsContainer();
        }
        return $children;
    }

    public function getRowDetailsContainer()
    {
        return $this->row_details_container;
    }

    /**
     *
     * @param Container $widget            
     */
    public function setRowDetailsContainer(Container $widget)
    {
        $this->row_details_container = $widget;
    }

    /**
     * Makes the table group rows by values of a column.
     * Each group will have a header and will be collapsible.
     *
     * It is a good idea to give the column, that will be used for grouping an explicit id. This id is then
     * what you need to specify in group_by_column_id. In most cases, the column used for grouping will be
     * hidden, because it does not make much sens to show it's values within every group as they are the same
     * in the group and are also visible in the group's title.
     *
     * Set "expand" to FALSE to collapse all groups initially. Set "show_count" to TRUE to include the number
     * of rows within the group in it's header.
     *
     * Example:
     * "group_rows": {
     * "group_by_column_id": "my_column_id",
     * "expand": true,
     * "show_count": true
     * "action_alias": "exface.Core.ShowWidget"
     * }
     *
     * @uxon-property group_rows
     * @uxon-type Object
     *
     * @param \stdClass $uxon_description_object            
     * @return DataTable
     */
    public function setGroupRows(\stdClass $uxon_description_object)
    {
        if (isset($uxon_description_object->group_by_column_id))
            $this->setRowGroupsByColumnId($uxon_description_object->group_by_column_id);
        if (isset($uxon_description_object->expand))
            $this->setRowGroupsExpand($uxon_description_object->expand);
        if (isset($uxon_description_object->show_count))
            $this->setRowGroupsShowCount($uxon_description_object->show_count);
        if (isset($uxon_description_object->action_alias))
            $this->setRowDetailsAction($uxon_description_object->action_alias);
        return $this;
    }

    public function getRowGroupsByColumnId()
    {
        return $this->row_groups_by_column_id;
    }

    public function setRowGroupsByColumnId($value)
    {
        $this->row_groups_by_column_id = $value;
    }

    public function getRowGroupsExpand()
    {
        return $this->row_groups_expand;
    }

    public function setRowGroupsExpand($value)
    {
        $this->row_groups_expand = $value;
    }

    public function getRowGroupsShowCount()
    {
        return $this->row_groups_show_count;
    }

    public function setRowGroupsShowCount($value)
    {
        $this->row_groups_show_count = $value;
    }

    public function getContextMenuEnabled()
    {
        return $this->context_menu_enabled;
    }

    /**
     * Set to FALSE to disable the context (right-click) menu for rows.
     *
     * @uxon-property context_menu_enabled
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return DataTable
     */
    public function setContextMenuEnabled($value)
    {
        $this->context_menu_enabled = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getShowFilterRow()
    {
        return $this->show_filter_row;
    }

    /**
     * Set to TRUE to show a special row with filters for each column (if supported by the template).
     *
     * This is a handy alternative to defining filter individually.
     *
     * @uxon-property show_filter_row
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return DataTable
     */
    public function setShowFilterRow($value)
    {
        $this->show_filter_row = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getHeaderSortMultiple()
    {
        return $this->header_sort_multiple;
    }

    /**
     * Set to TRUE to enable click-sorting via column headers for multiple columns simultanuosly (if supported by template)
     *
     * @uxon-property header_sort_multiple
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return DataTable
     */
    public function setHeaderSortMultiple($value)
    {
        $this->header_sort_multiple = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getWidth()
    {
        if (parent::getWidth()->isUndefined()) {
            $this->setWidth('max');
        }
        return parent::getWidth();
    }

    public function getRowDetailsAction()
    {
        return $this->row_details_action;
    }

    public function setRowDetailsAction($value)
    {
        $this->row_details_action = $value;
        return $this;
    }

    public function getHideToolbarTop()
    {
        return $this->hide_toolbar_top;
    }

    /**
     * Set to TRUE to hide the top toolbar or FALSE to show it.
     *
     * @uxon-property hide_toolbar_top
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveTopToolbar::setHideToolbarTop()
     */
    public function setHideToolbarTop($value)
    {
        $this->hide_toolbar_top = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getHideToolbarBottom()
    {
        return $this->hide_toolbar_bottom;
    }

    /**
     * Set to TRUE to hide the bottom toolbar or FALSE to show it.
     *
     * @uxon-property hide_toolbar_bottom
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveTopToolbar::setHideToolbarTop()
     */
    public function setHideToolbarBottom($value)
    {
        $this->hide_toolbar_bottom = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getHideToolbars()
    {
        return ($this->getHideToolbarTop() && $this->getHideToolbarBottom());
    }

    /**
     * Set to TRUE to hide the all toolbars.
     * Use hide_toolbar_top and hide_toolbar_bottom to control toolbar individually.
     *
     * @uxon-property hide_toolbars
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveTopToolbar::setHideToolbarTop()
     */
    public function setHideToolbars($value)
    {
        $this->setHideToolbarTop($value);
        $this->setHideToolbarBottom($value);
        return $this;
    }

    public function getShowRowNumbers()
    {
        return $this->show_row_numbers;
    }

    /**
     * Set to TRUE to show the row number for each row.
     *
     * @uxon-property show_row_numbers
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return DataTable
     */
    public function setShowRowNumbers($value)
    {
        $this->show_row_numbers = $value;
        return $this;
    }

    public function getNowrap()
    {
        return $this->nowrap;
    }

    /**
     * Set to TRUE to disable text wrapping in all columns.
     * Each column will have only one line then.
     *
     * @uxon-property nowrap
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\DataTable
     */
    public function setNowrap($value)
    {
        $this->nowrap = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getStriped()
    {
        return $this->striped;
    }

    /**
     * Set to TRUE to make the rows background color alternate.
     *
     * @uxon-property striped
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\DataTable
     */
    public function setStriped($value)
    {
        $this->striped = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function getAutoRowHeight()
    {
        return $this->auto_row_height;
    }

    /**
     * Set to FALSE to prevent automatic hight adjustment for rows.
     * Each row will have the height of one line.
     *
     * @uxon-property auto_row_height
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\DataTable
     */
    public function setAutoRowHeight($value)
    {
        $this->auto_row_height = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getMultiSelect()
    {
        return $this->multi_select;
    }

    /**
     * Set to TRUE to allow selecting multiple rows at a time and FALSE to force selection of exactly one row.
     *
     * @uxon-property multi_select
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportMultiSelect::setMultiSelect()
     */
    public function setMultiSelect($value)
    {
        $this->multi_select = $value;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings()
    {
        return null;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::getValues()
     */
    public function getValues()
    {
        // TODO set selected table rows programmatically
        /*
         * if ($this->getValue()){
         * return explode(EXF_LIST_SEPARATOR, $this->getValue());
         * }
         */
        return array();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::setValues()
     */
    public function setValues($expression_or_delimited_list)
    {
        // TODO set selected table rows programmatically
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::setValuesFromArray()
     */
    public function setValuesFromArray(array $values)
    {
        $this->setValue(implode(EXF_LIST_SEPARATOR, $values));
        return $this;
    }

    /**
     * Returns TRUE all rows should be selected initially and FALSE otherwise.
     * This only works with multi_select=true and no lazy loading.
     *
     * @return boolean
     */
    public function getMultiSelectAllSelected()
    {
        return $this->getMultiSelect() && ! $this->getLazyLoading() ? $this->multi_select_all_selected : false;
    }

    /**
     * Set to TRUE to make all rows be selected initially.
     * This only works with multi_select=true and no lazy loading!
     *
     * @uxon-property multi_select_all_selected
     * @uxon-type boolean
     *
     * @param boolean $true_or_false            
     * @return DataTable
     */
    public function setMultiSelectAllSelected($true_or_false)
    {
        $this->multi_select_all_selected = $true_or_false ? true : false;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::getNumberOfColumns()
     */
    public function getNumberOfColumns()
    {
        if (is_null($this->number_of_columns)) {
            $widget = $this;
            while ($widget->getParent()){
                $widget = $widget->getParent();
                if ($widget instanceof iLayoutWidgets && $widget->getNumberOfColumns()){
                    $this->number_of_columns = $widget->getNumberOfColumns();
                    break;
                }
            }
            if (is_null($this->number_of_columns)) {
                $this->number_of_columns = 4;
            }
            
            $dimension = $this->getWidth();
            if ($dimension->isRelative()) {
                $width = $dimension->getValue();
                if ($width === 'max') { $width = $this->number_of_columns; }
                if ($width < 1) { $width = 1; }
                if ($width < $this->number_of_columns) { $this->number_of_columns = $width; }
            }
        }
        return $this->number_of_columns;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::setNumberOfColumns()
     */
    public function setNumberOfColumns($value)
    {
        $this->number_of_columns = intval($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::getStackColumnsOnTabletsSmartphones()
     */
    public function getStackColumnsOnTabletsSmartphones()
    {
        return $this->column_stack_on_smartphones;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::setStackColumnsOnTabletsSmartphones()
     */
    public function setStackColumnsOnTabletsSmartphones($value)
    {
        $this->column_stack_on_smartphones = BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::getStackColumnsOnTabletsTablets()
     */
    public function getStackColumnsOnTabletsTablets()
    {
        return $this->column_stack_on_tablets;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iLayoutWidgets::setStackColumnsOnTabletsTablets()
     */
    public function setStackColumnsOnTabletsTablets($value)
    {
        $this->column_stack_on_tablets = BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::addWidget()
     */
    public function addWidget(AbstractWidget $widget, $position = NULL)
    {}

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::addWidgets()
     */
    public function addWidgets(array $widgets)
    {}

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::getWidgets()
     */
    public function getWidgets()
    {}

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::getInputWidgets()
     */
    public function getInputWidgets($depth = null)
    {}

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::removeWidgets()
     */
    public function removeWidgets()
    {}

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::setWidgets()
     */
    public function setWidgets(array $widget_or_uxon_array)
    {}

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::countWidgets()
     */
    public function countWidgets()
    {}

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::findChildrenByAttribute()
     */
    public function findChildrenByAttribute(Attribute $attribute)
    {}
}
?>