<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveContextMenu;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Exceptions\Widgets\WidgetLogicError;

/**
 * Renders data as a table with filters, columns, and toolbars.
 *
 * Example showing attributes from the metamodel:
 * 
 * ```json
 *  {
 *      "widget_type": "DataTable",
 *      "object_alias": "exface.Core.ATTRIBUTE",
 *      "filters": [
 *          {
 *              "attribute_alias": "OBJECT"
 *          },
 *          {
 *              "attribute_alias": "OBJECT__DATA_SOURCE"
 *          }
 *      ],
 *      "columns": [
 *          {
 *              "attribute_alias": "OBJECT__LABEL"
 *          },
 *          {
 *              "attribute_alias": "LABEL"
 *          },
 *          {
 *              "attribute_alias": "ALIAS"
 *          },
 *          {
 *              "attribute_alias": "RELATED_OBJ__LABEL",
 *              "caption": "Relation to"
 *          }
 *      ],
 *      "sorters": [
 *          {
 *              "attribute_alias": "OBJECT__LABEL",
 *              "direction": "desc"
 *          }
 *      ],
 *      "buttons": [
 *          {
 *              "action_alias": "exface.Core.UpdateData"
 *          },
 *          {
 *              "action_alias": "exface.Core.ShowObjectCreateDialog"
 *          },
 *          {
 *              "action_alias": "exface.Core.ShowObjectEditDialog",
 *              "bind_to_double_click": true
 *          },
 *          {
 *              "action_alias": "exface.Core.DeleteObject"
 *          }
 *      ]
 *  }
 * 
 * ```
 * ## Editable columns
 *  
 * Columns of the DataTable can also be made editable by configuring an input widget in the 
 * `editor` property of the column. 
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTable extends Data implements iFillEntireContainer, iSupportMultiSelect, iHaveContextMenu
{

    private $show_filter_row = false;

    private $show_row_numbers = false;

    private $multi_select = false;

    private $multi_select_all_selected = false;

    private $striped = true;

    private $nowrap = true;

    private $auto_row_height = true;

    private $row_details_container = null;

    private $row_details_action = 'exface.Core.ShowWidget';

    private $row_grouper = null;
    
    private $context_menu_enabled = true;

    private $header_sort_multiple = false;

    private $context_menu = null;

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
     * Most templates will render an expand-button in each row, allowing to expand/collapse the detail widget.
     * This only works with interactiv templates (e.g. HTML-templates)
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
     * ```
     *
     * @uxon-property row_details
     * @uxon-type \exface\Core\Widgets\Container
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
     * 
     * Rows with equal values in the column being grouped by will be visually grouped together and separated
     * from other groups via a group header, which will show the grouped value and additional information if
     * configured. Most templates will support collapsing and expanding groups.
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
     *  ...
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
     * @uxon-type \exface\Core\Widgets\DataRowGrouper
     *
     * @param UxonObject $uxon            
     * @return DataTable
     */
    public function setRowGrouper(UxonObject $uxon)
    {
        $grouper = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'DataRowGrouper');
        if (! ($grouper instanceof DataRowGrouper)) {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid widget type for the data row grouping: expecting DataRowGrouper or derivatives, received ' . $grouper->getWidgetType() . ' instead!');
        }
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
        return is_null($this->row_grouper) ? false : true;
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
        $this->context_menu_enabled = \exface\Core\DataTypes\BooleanDataType::cast($value);
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
        $this->show_filter_row = \exface\Core\DataTypes\BooleanDataType::cast($value);
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
        $this->header_sort_multiple = \exface\Core\DataTypes\BooleanDataType::cast($value);
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
        $this->nowrap = \exface\Core\DataTypes\BooleanDataType::cast($value);
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
        $this->striped = \exface\Core\DataTypes\BooleanDataType::cast($value);
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
        $this->auto_row_height = \exface\Core\DataTypes\BooleanDataType::cast($value);
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
    
    public function getValueWithDefaults()
    {
        // TODO return the UID of programmatically selected row
        return null;
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
        $this->setValue(implode($this->getUidColumn()->getAttribute()->getValueListDelimiter(), $values));
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
     * @see \exface\Core\Widgets\Data::getToolbars()
     */
    public function getToolbars()
    {
        $toolbars = parent::getToolbars();
        if ($this->hasAggregations()) {
            $toolbars[0]->setIncludeGlobalActions(false)->setIncludeObjectBasketActions(false);
        }
        return $toolbars;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::getConfiguratorWidgetType()
     */
    public function getConfiguratorWidgetType()
    {
        return 'DataTableConfigurator';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueDataType()
     */
    public function getValueDataType()
    {
        return $this->getUidColumn()->getDataType();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::hasValue()
     */
    public function hasValue()
    {
        return is_null($this->getValue()) ? false : true;
    }

}
?>