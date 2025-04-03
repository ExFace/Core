<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
interface iSupportMultiSelect extends WidgetInterface
{

    /**
     * Returns TRUE if multiselect is enabled for this widget and FALSE otherwise
     *
     * @return boolean
     */
    public function getMultiSelect() : bool;

    /**
     * Set to TRUE to enable multiselect for this widget.
     *
     * @param boolean $value    
     * @return iSupportMultiSelect        
     */
    public function setMultiSelect(bool $value) : iSupportMultiSelect;
}