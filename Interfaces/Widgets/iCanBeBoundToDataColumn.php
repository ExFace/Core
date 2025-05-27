<?php
namespace exface\Core\Interfaces\Widgets;

/**
 * Model entities (e.g. widgets) implementing this interface can reference a single column of data.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iCanBeBoundToDataColumn
{
    /**
     * Returns the name of the corresponding column of the data sheet shown.
     *
     * @return string|NULL
     */
    public function getDataColumnName();
    
    /**
     * Returns TRUE if this widget is actually bound to a data column.
     * 
     * @return bool
     */
    public function isBoundToDataColumn() : bool;
}