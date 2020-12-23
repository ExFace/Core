<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

/**
 * Interface for widgets, that can be bound to a single column of a data sheet.
 * 
 * In contrast to `iShowSingleAttribute` these widgets may also show data not
 * directly related to the model: like formulas, static values, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iShowDataColumn extends WidgetInterface
{

    /**
     * Returns the name of the corresponding column of the data sheet shown.
     *
     * @return string
     */
    public function getDataColumnName();
    
    /**
     * Returns TRUE if this widget is actually bound to a data column.
     * 
     * @return bool
     */
    public function isBoundToDataColumn() : bool;

    /**
     * Sets the name of the corresponding column of the data sheet shown
     *
     * @param string $value            
     * @return \exface\Core\Interfaces\Widgets\iShowDataColumn
     */
    public function setDataColumnName($value);
}