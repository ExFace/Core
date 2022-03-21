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
     * @param bool $value
     * @return iShowData
     */
    public function setEditable(bool $value = true) : iShowData;
}