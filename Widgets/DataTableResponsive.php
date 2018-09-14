<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;

/**
 * A responsive table collapses it's overflowing columns into vertical lists on small screens.
 * 
 * This special widget allows to control the behavior of overflowing columns.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTableResponsive extends DataTable
{
    private $overflowCollapsible = null;
    
    private $overflowCollapsed = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::getColumnDefaultWidgetType()
     */   
    public function getColumnDefaultWidgetType() : string
    {
        return 'DataColumnResponsive';
    }
    
    /**
     *
     * @param bool $default
     * @return bool
     */
    public function getOverflowCollapsible(bool $default = true) : bool
    {
        return $this->overflowCollapsible === null ? $default : $this->overflowCollapsible;
    }
    
    /**
     * Makes the details display collapsible by default.
     *
     * I.e. high priority data is shown in "normal" rows while overflow data is hidden
     * by default and can be shown by expanding the rows or a similar action.
     *
     * @uxon-property overflow_collapsible
     * @uxon-type boolean
     *
     * @param string|int|bool $trueOrFalse
     * @return DataTableResponsive
     */
    public function setOverflowCollapsible($trueOrFalse) : DataTableResponsive
    {
        $this->overflowCollapsible = BooleanDataType::cast($trueOrFalse);
        return $this;
    }
    
    /**
     * 
     * @param bool $default
     * @return bool
     */
    public function getOverflowCollapsed(bool $default = false) : bool
    {
        if ($this->getOverflowCollapsible(true) === false) {
            return false;
        }
        return $this->overflowCollapsed === null ? $default : $this->overflowCollapsed;
    }
    
    /**
     * Makes the details display collapsed by default.
     * 
     * I.e. high priority data is shown in "normal" rows while overflow data is hidden
     * by default and can be shown by expanding the rows or a similar action.
     * 
     * @uxon-property overflow_collapsed
     * @uxon-type boolean
     * 
     * @param string|int|bool $trueOrFalse
     * @return DataTableResponsive
     */
    public function setOverflowCollapsed($trueOrFalse) : DataTableResponsive
    {
        $val = BooleanDataType::cast($trueOrFalse);
        $this->overflowCollapsed = $val;
        if ($val === true) {
            $this->setOverflowCollapsible(true);
        }
        return $this;
    }
}
?>