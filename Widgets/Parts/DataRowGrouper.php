<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Exceptions\Widgets\WidgetNotFoundError;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\Widgets\Traits\DataWidgetPartTrait;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\DataTable;
use exface\Core\Widgets\DataColumn;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveCaption;
use exface\Core\Widgets\Traits\iHaveCaptionTrait;

/**
 * This widget part is used to group rows in a data table.
 * 
 * The widget part itself basically represents the group headers. It also defines,
 * how the rows are grouped: what is the grouping criteria, if and how sorting
 * is to be performed, etc.
 * 
 * Example:
 * 
 * ```json
 * {
 *  "widget_type": "DataTable",
 *  "row_grouper": {
 *      "group_by_attribute_alias": "MY_ATTRIBUTE",
 *      "expand_all_groups": true,
 *      "show_counter": true
 *  }
 * }
 * 
 * ```
 * 
 * @method DataTable getParent()
 *
 * @author Andrej Kabachnik
 *        
 */
class DataRowGrouper implements WidgetPartInterface, iHaveCaption
{
    use DataWidgetPartTrait;
    use iHaveCaptionTrait {
        getCaption as getCaptionViaTrait;
    }
    
    const EXPAND_ALL_GROUPS = 'ALL';
    const EXPAND_FIRST_GROUP = 'FIRST';
    const EXPAND_NO_GROUPS = 'NONE';
    
    /**
     * @var string|null
     */
    private $group_by_column_id = null;
    
    /**
     * 
     * @var string|null
     */
    private $group_by_attribute_alias = null;
    
    /**
     * 
     * @var DataColumn|null
     */
    private $group_by_column = null;
    
    /**
     * @var boolean
     */
    private $expand_groups = self::EXPAND_ALL_GROUPS;
    
    /**
     * 
     * @var boolean
     */
    private $show_counter = false;
    
    /**
     * 
     * @throws WidgetConfigurationError
     * @return DataTable
     */
    public function getDataTable()
    {
        $table = $this->getDataWidget();
        if (! ($table instanceof DataTable)) {
            throw new WidgetConfigurationError($this, 'A DataRowGrouper cannot be used outside of a DataTable widget!', '6Z5MAVK');
        }
        return $table;
    }
    
    /**
     * 
     * @return string
     */
    protected function getGroupByColumnId()
    {
        if (is_null($this->group_by_column_id)) {
            return $this->getGroupByColumn()->getId();
        }
        return $this->group_by_column_id;
    }
    
    /**
     * Specifies an existing column for grouping - presuming the column has an explicit id.
     * 
     * Using column ids groups can be created over calculated columns. For columns with
     * attributes from the meta model, specifying the attribute_alias is simpler.
     * 
     * @uxon-property group_by_column_id
     * @uxon-type uxon:.columns..id
     * 
     * @param string $value
     * @return DataRowGrouper
     */
    public function setGroupByColumnId($value)
    {
        $this->group_by_column = null;
        $this->group_by_column_id = $value;
        return $this;
    }
    
    /**
     * 
     * @throws WidgetNotFoundError
     * @throws WidgetLogicError
     * @throws WidgetConfigurationError
     * 
     * @return \exface\Core\Widgets\DataColumn
     */
    public function getGroupByColumn()
    {
        if ($this->group_by_column === null) {
            if (! is_null($this->group_by_attribute_alias)) {
                if (! is_null($this->group_by_column_id)) {
                    throw new WidgetConfigurationError($this->getDataTable(), 'Alternative properties "group_by_attribute_alias" and "group_by_column_id" are defined at the same time for a DataRowGrouper widget: please use only one of them!', '6Z5MAVK');
                }
                if (! $col = $this->getDataTable()->getColumnByAttributeAlias($this->group_by_attribute_alias)) {
                    throw new WidgetLogicError($this->getDataTable(), 'No data column "' . $this->group_by_attribute_alias . '" could be added automatically by the DataRowGrouper: try to add it manually to the DataTable.');
                }
            } elseif (! is_null($this->group_by_column_id)) {
                if (! $col = $this->getDataTable()->getColumn($this->group_by_column_id)) {
                    throw new WidgetNotFoundError('Cannot find the column "' . $this->group_by_column_id . '" to group rows by!', '6Z5MAVK');
                }
            } else {
                throw new WidgetConfigurationError($this->getDataTable(), 'No column to group by can be found for DataRowGrouper!', '6Z5MAVK');
            }
            $this->group_by_column = $col;
        }
        
        return $this->group_by_column;
    }
    
    /**
     * 
     * @return boolean
     */
    public function getExpandAllGroups()
    {
        return $this->expand_groups === self::EXPAND_ALL_GROUPS;
    }
    
    /**
     * Set to FALSE to collapse all groups when loading data - TRUE by default.
     * 
     * @uxon-property expand_all_groups
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\Parts\DataRowGrouper
     */
    public function setExpandAllGroups($true_or_false)
    {
        $this->expand_groups = BooleanDataType::cast($true_or_false) ? self::EXPAND_ALL_GROUPS : self::EXPAND_NO_GROUPS;
        return $this;
    }
    
    /**
     *
     * @return boolean
     */
    public function getExpandFirstGroupOnly()
    {
        return $this->expand_groups === self::EXPAND_FIRST_GROUP;
    }
    
    /**
     * Set to FALSE to collapse all groups when loading data - TRUE by default.
     *
     * @uxon-property expand_all_groups
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\Parts\DataRowGrouper
     */
    public function setExpandFirstGroupOnly($true_or_false)
    {
        $this->expand_groups = BooleanDataType::cast($true_or_false) ? self::EXPAND_FIRST_GROUP : self::EXPAND_ALL_GROUPS;
        return $this;
    }
    
    /**
     * 
     * @return boolean
     */
    public function getShowCounter()
    {
        return $this->show_counter;
    }
    
    /**
     * Set to TRUE to show the numer of grouped rows in each group header - FALSE by default.
     * 
     * @uxon-property show_counter
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\Parts\DataRowGrouper
     */
    public function setShowCounter($true_or_false)
    {
        $this->show_counter = BooleanDataType::cast($true_or_false);
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getGroupByAttributeAlias()
    {
        if (is_null($this->group_by_attribute_alias)) {
            return $this->getGroupByColumn()->getAttributeAlias();
        }
        return $this->group_by_attribute_alias;
    }
    
    /**
     * Specifies the attribute to group over - a corresponding (hidden) data column will be added automatically.
     * 
     * If there already is a column with this attribute alias, it will be used for grouping
     * instead of creating a new one.
     * 
     * @uxon-property group_by_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return \exface\Core\Widgets\Parts\DataRowGrouper
     */
    public function setGroupByAttributeAlias($alias)
    {
        $this->group_by_attribute_alias = $alias;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            'group_by_attribute_alias' => $this->getGroupByAttributeAlias()
        ]);
        
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveCaption::getCaption()
     */
    public function getCaption()
    {
        if (! $cap = $this->getCaptionViaTrait()) {
            $cap = $this->getGroupByColumn()->getCaption();
            $this->setCaption($cap);
        }
        return $cap;
    }
}