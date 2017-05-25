<?php
namespace exface\Core\Interfaces\Widgets;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
interface iSupportMultiSelect extends iHaveValues
{

    /**
     * Returns TRUE if multiselect is enabled for this widget and FALSE otherwise
     *
     * @return boolean
     */
    public function getMultiSelect();

    /**
     * Set to TRUE to enable multiselect for this widget.
     *
     * @param boolean $value            
     */
    public function setMultiSelect($value);
}