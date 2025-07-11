<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveContextMenu;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\Interfaces\Widgets\iTakeInputAsDataSubsheet;
use exface\Core\Widgets\Parts\DataRowGrouper;
use exface\Core\Widgets\Traits\EditableTableTrait;
use exface\Core\Widgets\Traits\DataTableTrait;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iCanWrapText;
use exface\Core\Interfaces\Widgets\iCanEditData;
use exface\Core\Interfaces\Widgets\iCanBeDragAndDropSource;

/**
 * Renders data as a table with filters, columns, and toolbars.
 * 
 * ## Regular tables
 * 
 * Example showing attributes from the metamodel:
 * 
 * ```json
 *  {
 *      "widget_type": "DataTable",
 *      "object_alias": "exface.Core.ATTRIBUTE",
 *      "filters": [
 *          {"attribute_alias": "OBJECT"},
 *          {"attribute_alias": "OBJECT__DATA_SOURCE"}
 *      ],
 *      "columns": [
 *          {"attribute_alias": "OBJECT__LABEL"},
 *          {"attribute_alias": "LABEL"},
 *          {"attribute_alias": "RELATED_OBJ__LABEL", "caption": "Relation to"}
 *      ],
 *      "sorters": [
 *          {"attribute_alias": "OBJECT__LABEL", "direction": "desc"}
 *      ],
 *      "buttons": [
 *          {"action_alias": "exface.Core.UpdateData"},
 *          {"action_alias": "exface.Core.ShowObjectCreateDialog"},
 *          {"action_alias": "exface.Core.ShowObjectEditDialog", "bind_to_double_click": true},
 *          {"action_alias": "exface.Core.DeleteObject"}
 *      ]
 *  }
 * 
 * ```
 * 
 * ## Common settings
 * 
 * ### Handling long text values: wrapping and truncating
 * 
 * By default each textual cell of a table will only contain a single line. Thus, all rows will
 * will have the same height and will be easy to read and scroll. On the other hand, columns with
 * long texts will produce very wide columns. There are multiple options to achieve compromises:
 * 
 * - use `nowrap:false` for certain columns or the entire table. The table will attempt to
 * optimize column width wrapping long values. How exactly this will look, largely depends on the
 * facade being used and your specific data. In any case, rows will get "thicker" and may all have
 * different height, which will make the table harder to read.
 * - use `Text` as `cell_widget` and configure `multi_line` behavior explicitly. In particular, you
 * can limit the number of lines produces via `multi_line_max_lines`.
 * - use `width_max` on columns if supported by the facade used
 * - use fixed `width` columns, which will truncate the value, but keep the full value as tooltip
 * - use the formula `=Truncate(YOUR_ATTRIBUTE, 60)` to cut off the value after a certain length,
 * which will probably produce the most stable results, but will also truncate tooltips.
 * 
 * ## Editable tables
 *  
 * Columns of the DataTable can also be made editable. Changes can be saved either by adding
 * `SaveData`-actions to the `buttons` of the table or by using the table within a `Form` widget.
 * 
 * There are multiple ways to make a table editable:
 * 
 * - Set the table property `editable: true`. This will automatically render editors for all
 * columns, that are bound to an editable model attribute.
 * - Set the property `editable: true` for a specific `DataColunGroup` (if column groups are used).
 * This will automatically render editors for all columns of the group, that are bound to an editable 
 * model attribute.
 * - Set the property `editable: true` for a specific `DataColumn`. This will force the column to
 * use the default editor for the attribute as cell widget - similar to what `ShowObjectXXXDialog`
 * action will do.
 * - Set the `cell_widget` for a column to an active Input widget.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTable extends Data implements 
    iCanEditData,
    iFillEntireContainer, 
    iSupportMultiSelect, 
    iHaveContextMenu,
    iTakeInputAsDataSubsheet,
    iCanWrapText,
    iCanBeDragAndDropSource
{
    use DataTableTrait;
    use EditableTableTrait;
    
    /**
     * Empty the table
     *
     * @uxon-property empty
     *
     * @var string
     */
    const FUNCTION_EMPTY = 'empty';

    private $show_filter_row = null;

    private $show_row_numbers = false;

    private $multi_select = false;

    private $multi_select_all_selected = false;
    
    private $multi_select_sync_attribute = null;

    private $multi_select_saved_on_nav = null;

    private $auto_row_height = true;
    
    private $auto_columnn_width = true;

    private $row_details_container = null;

    private $row_details_action = 'exface.Core.ShowWidget';

    private $row_grouper = null;
    
    private $context_menu_enabled = true;

    private $header_sort_multiple = false;

    private $context_menu = null;
    
    private $freeze_columns = 0;
    
    private $select_single_result = false;
    
    private $height_in_rows = null;
    
    private $drag_to_other_widgets = false;

    function hasRowDetails()
    {
        if (! $this->row_details_container)
            return false;
        else
            return true;
    }

    /**
     * Makes each row have a collapsible detail container with arbitrary widgets.
     * 
     * Most facades will render an expand-button in each row, allowing to expand/collapse the detail widget.
     * This only works with interactiv facades (e.g. HTML-facades)
     * 
     * The widget type of the details-widget can be omitted. It defaults to Container in this case.
     * 
     * Example:
     * ```json 
     * {
     *      height: 5,
     *      widgets: [  
     *          {
     *              "widget_type": "DataTable",
     *              "object_alias": "my.App.RelatedObject",
     *              "filters": [
     *                  {
     *                      "attribute_alias": "RELATION_TO_OBJECT_OF_PARENT_TABLE"
     *                  }
     *              ],
     *              "columns": []
     *          }
     *      ]
     * }
     * 
     * ```
     *
     * @uxon-property row_details
     * @uxon-type \exface\Core\Widgets\Container
     * @uxon-template {"height": "", "widgets": [{"": ""}]}
     *
     * @param UxonObject $detail_widget
     * @return boolean
     */
    public function setRowDetails(UxonObject $detail_widget)
    {
        $page = $this->getPage();
        $widget = WidgetFactory::createFromUxon($page, $detail_widget, $this, 'Container');
        if ($widget instanceof Container) {
            $container = $widget;
        } else {
            $container = $this->getPage()->createWidget('Container', $this);
            $container->addWidget($widget);
        }
        $this->setRowDetailsContainer($container);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::getChildren()
     */
    public function getChildren() : \Iterator
    {
        foreach (parent::getChildren() as $child) {
            yield $child;
        }
        
        if ($this->hasRowDetails()) {
            yield $this->getRowDetailsContainer();
        }
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
     * 
     * Rows with equal values in the column being grouped by will be visually grouped together and separated
     * from other groups via a group header, which will show the grouped value and additional information if
     * configured. Most facades will support collapsing and expanding groups.
     *
     * You can group by column id if the data column already exists in the table or by attribute alias to
     * make the system add a corresponding hidden column automatically.
     *
     * Set "expand" to FALSE to collapse all groups initially. Set "show_count" to TRUE to include the number
     * of rows within the group in it's header.
     *
     * 
     * Example:
     * 
     * ```json
     * {
     *  "widget_type": "DataTable",
     *  "row_grouper": {
     *      "group_by_attribute_alias": "MY_ATTRIBUTE",
     *      "expand_all_groups": true,
     *      "show_count": true
     *  }
     * }
     * 
     * ```
     *
     * @uxon-property row_grouper
     * @uxon-type \exface\Core\Widgets\Parts\DataRowGrouper
     * @uxon-template {"group_by_attribute_alias": ""}
     *
     * @param UxonObject $uxon            
     * @return DataTable
     */
    public function setRowGrouper(UxonObject $uxon)
    {
        $grouper = new DataRowGrouper($this, $uxon);
        $this->row_grouper = $grouper;
        return $this;
    }
    
    /**
     * Returns the DataRowGrouper widget if row grouping is configured or throws exception.
     * 
     * @throws WidgetLogicError
     * @return DataRowGrouper
     */
    public function getRowGrouper()
    {
        if (is_null($this->row_grouper)) {
            throw new WidgetLogicError($this, 'Property row_grouper not set prior to grouper initialization!');
        }
        return $this->row_grouper;
    }
    
    /**
     * Returns TRUE if row grouping is enabled for this table and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasRowGroups()
    {
        return $this->row_grouper !== null;
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
     * @uxon-default true
     *
     * @param boolean $value            
     * @return DataTable
     */
    public function setContextMenuEnabled($value)
    {
        $this->context_menu_enabled = BooleanDataType::cast($value);
        return $this;
    }

    public function getShowFilterRow()
    {
        return $this->show_filter_row;
    }

    /**
     * Set to TRUE to show a special row with filters for each column (if supported by the facade).
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
        $this->show_filter_row = BooleanDataType::cast($value);
        return $this;
    }

    public function getHeaderSortMultiple()
    {
        return $this->header_sort_multiple;
    }

    /**
     * Set to TRUE to enable click-sorting via column headers for multiple columns simultanuosly (if supported by facade)
     *
     * @uxon-property header_sort_multiple
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param boolean $value            
     * @return DataTable
     */
    public function setHeaderSortMultiple($value)
    {
        $this->header_sort_multiple = BooleanDataType::cast($value);
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

    /**
     * The action to render the row details.
     * 
     * @uxon-property row_details_action
     * @uxon-type metamodel:action
     * @uxon-default exface.Core.ShowWidget
     * 
     * @param string $value
     * @return \exface\Core\Widgets\DataTable
     */
    public function setRowDetailsAction($value)
    {
        $this->row_details_action = $value;
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
     * @uxon-default false
     *
     * @param boolean $value            
     * @return DataTable
     */
    public function setShowRowNumbers($value)
    {
        $this->show_row_numbers = $value;
        return $this;
    }
    
    /**
     *
     * @return boolean
     */
    public function getAutoRowHeight() : bool
    {
        return $this->auto_row_height;
    }

    /**
     * Set to FALSE to prevent automatic hight adjustment for rows.
     * Each row will have the height of one line.
     *
     * @uxon-property auto_row_height
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\DataTable
     */
    public function setAutoRowHeight(bool $value)
    {
        $this->auto_row_height = $value;
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function getAutoColumnWidth() : bool
    {
        return $this->auto_columnn_width;
    }

    /**
     * Set to FALSE to prevent automatic width adjustment for columns.
     * 
     * The exact behavior of this depends on the facade used, but most facades will distribute columns
     * evenly in this case. 
     * 
     * @uxon-property auto_column_width
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param boolean $value
     * @return \exface\Core\Widgets\DataTable
     */
    public function setAutoColumnWidth(bool $value)
    {
        $this->auto_columnn_width = $value;
        return $this;
    }

    public function getMultiSelect() : bool
    {
        return $this->multi_select;
    }

    /**
     * Set to TRUE to allow selecting multiple rows at a time and FALSE to force selection of exactly one row.
     *
     * @uxon-property multi_select
     * @uxon-type boolean
     * @uxon-default false
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportMultiSelect::setMultiSelect()
     */
    public function setMultiSelect(bool $value) : iSupportMultiSelect
    {
        $this->multi_select = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getSelectSingleResult() : bool
    {
        return $this->select_single_result;
    }
    
    /**
     * Set to TRUE to automatically select a row if it is the only row in the table.
     *
     * @uxon-property select_single_result
     * @uxon-type boolean
     * @uxon-default false
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportMultiSelect::setMultiSelect()
     */
    public function setSelectSingleResult(bool $value)
    {
        $this->select_single_result = $value;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings() : ?iContainOtherWidgets
    {
        return null;
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
     * @uxon-default false
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
     * @return DataItemMenu
     */
    public function getContextMenu()
    {
        if (is_null($this->context_menu)) {
            $this->context_menu = WidgetFactory::create($this->getPage(), 'DataItemMenu', $this);
        }
        return $this->context_menu;
    }

    /**
     * 
     * @param DataItemMenu|UxonObject $widget_or_uxon_object
     * @return \exface\Core\Widgets\DataTable
     */
    public function setContextMenu($widget_or_uxon_object)
    {
        if ($widget_or_uxon_object instanceof DataItemMenu) {
            $menu = $widget_or_uxon_object;
        } elseif ($widget_or_uxon_object instanceof UxonObject) {
            if (! $widget_or_uxon_object->hasProperty('widget_type')) {
                $widget_or_uxon_object->setProperty('widget_type', 'DataItemMenu');
            }
            $menu = WidgetFactory::createFromUxon($this->getPage(), $widget_or_uxon_object, $this);
        }
        $this->context_menu = $menu;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::getConfiguratorWidgetType()
     */
    public function getConfiguratorWidgetType() : string
    {
        return 'DataTableConfigurator';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        
        $uxon->setProperty('display_only', $this->isDisplayOnly());
        $uxon->setProperty('required', $this->isRequired());
        $uxon->setProperty('multi_select', $this->getMultiSelect());
        $uxon->setProperty('multi_select_all_selected', $this->getMultiSelectAllSelected());
        $uxon->setProperty('nowrap', $this->getNowrap());
        $uxon->setProperty('striped', $this->getStriped());
        $uxon->setProperty('show_row_numbers', $this->getShowRowNumbers());
        $uxon->setProperty('auto_row_height', $this->getAutoRowHeight());
        $uxon->setProperty('header_sort_multiple', $this->getHeaderSortMultiple());
        
        if ($this->hasRowGroups() === true) {
            $uxon->setProperty('row_grouper', $this->getRowGrouper()->exportUxonObject());
        }
        
        if ($this->hasRowDetails() === true) {
            $uxon->setProperty('row_details_action', $this->getRowDetailsAction());
            $uxon->setProperty('row_details', $this->getRowDetailsContainer()->exportUxonObject());
        }
        
        $uxon = $uxon->extend($this->exportUxonForEditableProperties());
        
        return $uxon;
    }
    
    /**
     * Set the attribute alias all rows with the same value in corresponding column should be selected when one row gets selected.
     * 
     * @uxon-property multi_select_sync_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return \exface\Core\Widgets\DataTable
     */
    public function setMultiSelectSyncAttributeAlias(string $value)
    {
        $this->setMultiSelect(true);
        $this->multi_select_sync_attribute = $value;
        return $this;
    }
    
    /**
     * Get the attribute alias all rows with the same value in corresponding column should be selected when one row gets selected.
     *
     * @return string
     */
    public function getMultiSelectSyncAttributeAlias()
    {
        return $this->multi_select_sync_attribute;
    }

    /**
     * 
     * @return bool
     */
    public function isMultiSelectSavedOnNavigation() : bool
    {
        return $this->multi_select_saved_on_nav ?? false;
    }

    /**
     * Set to TRUE to make the table collect selected items even if they become inivsible due to pagination or filtering
     * 
     * This is useful for tables, that are used to collect items for a future common action - 
     * like a shopping cart, so to say. In particular, this feature is always used in the DataLookupDialog.
     * 
     * @uxon-property multi_select_saved_on_navigation
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return \exface\Core\Widgets\DataTable
     */
    public function setMultiSelectSavedOnNavigation(bool $value) : DataTable
    {
        $this->multi_select_saved_on_nav = $value;
        return $this;
    }
    
    /**
     * 
     * @return int
     */
    public function getFreezeColumns() : int
    {
        return $this->freeze_columns;
    }
    
    /**
     * Freeze the first X columns from the left (X is the value of this property)
     * 
     * @uxon-property freeze_columns
     * @uxon-type integer
     * @uxon-default 0 
     * 
     * @param int $value
     * @return DataTable
     */
    public function setFreezeColumns(int $value) : DataTable
    {
        $this->freeze_columns = $value;
        return $this;
    }
    
    /**
     * 
     * @param DataColumn $col
     * @return bool
     */
    public function isFrozen(DataColumn $col) : bool
    {
        $frozen = $this->getFreezeColumns();
        if ($frozen === 0) {
            return false;
        }
        $visibleCnt = 0;
        foreach ($this->getColumns() as $col) {
            if ($col->isHidden()) {
               continue; 
            }
            $visibleCnt++;
            return $visibleCnt <= $frozen ? true : false;
        }
    }
    
    /**
     * 
     * @return int|NULL
     */
    public function getHeightInRows() : ?int
    {
        return $this->height_in_rows;
    }
    
    /**
     * Abjust the height of the widget to always show this number of rows
     * 
     * @uxon-property height_in_rows
     * @uxon-type integer
     * 
     * @param int $value
     * @return DataTable
     */
    public function setHeightInRows(int $value) : DataTable
    {
        $this->setHeight(null);
        $this->height_in_rows = $value;
        return $this;
    }
    
    /**
     * Sets the height of the widget.
     * Set to `1` for default widget height in a facade or `max` for maximum height possible.
     *
     * The height can be specified either in
     * - facade-specific relative units (e.g. `height: 2` makes the widget twice as high
     * as the default width of a widget in the current facade)
     * - percent (e.g. `height: 50%` will make the widget take up half the available space)
     * - any other facade-compatible units (e.g. `height: 200px` will work in CSS-based facades)
     *
     * @uxon-property height
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WidgetInterface::setHeight()
     */
    public function setHeight($value)
    {
        $this->height_in_rows = null;
        return parent::setHeight($value);
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see iCanBeDragAndDropSource
     */
    public function isDragSource() : bool
    {
        return $this->getDragToOtherWidgets() === true;
    }
    
    /**
     * 
     * @return bool
     */
    public function getDragToOtherWidgets() : bool
    {
        return $this->drag_to_other_widgets;
    }
    
    /**
     * Set to TRUE to enable users to drag rows to widgets, that accept drop actions
     * 
     * @uxon-property drag_to_other_widgets
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return DataTable
     */
    public function setDragToOtherWidgets(bool $value) : DataTable
    {
        $this->drag_to_other_widgets = $value;
        return $this;
    }
}