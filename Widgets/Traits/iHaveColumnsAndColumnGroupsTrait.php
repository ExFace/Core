<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataColumnGroup;
use exface\Core\Interfaces\Widgets\iHaveColumnGroups;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Factories\WidgetFactory;

/**
 * Trait for widgets with columns organized in groups (like DataGrid, DataTable, etc.)
 * 
 * @author Andrej Kabachnik
 *
 */
trait iHaveColumnsAndColumnGroupsTrait 
{
    /** @var DataColumnGroup[] */
    private $column_groups = array();
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::init()
     */
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
            foreach ($this->createDefaultColumns() as $col) {
                $this->addColumn($col);
            }
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
     * Returns an array of columns to be used by default (if neither columns nor column
     * groups were added to the widget explicitly).
     * 
     * Override this method to add default columns: e.g. regular Data widets would
     * create columns for default display attributes here.
     * 
     * @return DataColumn[]
     */
    public function createDefaultColumns() : array
    {
        return [];
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
     * @return iHaveColumnGroups
     */
    public function addColumnGroup(DataColumnGroup $column_group)
    {
        $this->column_groups[] = $column_group;
        return $this;
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
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::getColumnDefaultWidgetType()
     */
    public function getColumnDefaultWidgetType() : string
    {
        return 'DataColumn';
    }
    
    /**
     * Returns TRUE if the columns should contain editors by default or FALSE for displays
     * 
     * @return bool
     */
    abstract public function isEditable() : bool;
}