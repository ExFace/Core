<?php
namespace exface\Core\Interfaces\Widgets;

/**
 * This is a common interface for data widgets
 * 
 * @author Andrej Kabachnik
 *
 */
interface iShowData extends iHaveColumns, iHaveFilters, iHaveConfigurator
{
    /**
     * Returns true, if the data table contains at least one editable column
     *
     * @return boolean
     */
    public function isEditable() : bool;
    
    /**
     * Set to TRUE to make the table editable or add a column with an editor.
     * FALSE by default.
     *
     * @uxon-property editable
     * @uxon-type boolean
     *
     * @return \exface\Core\Widgets\Data
     */
    public function setEditable($value = true) : iShowData;
}