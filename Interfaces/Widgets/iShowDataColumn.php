<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iShowDataColumn extends WidgetInterface
{

    /**
     * Returns the name of the corresponding column of the data sheet shown
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