<?php
namespace exface\Core\Interfaces\Widgets;

/**
 * This is a common interface for widgets with optional editing
 * 
 * @author Andrej Kabachnik
 *
 */
interface iCanBeEditable
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
     * @return iCanBeEditable
     */
    public function setEditable(bool $value = true) : iCanBeEditable;
}