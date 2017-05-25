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
     * Sets the name of the corresponding column of the data sheet shown
     *
     * @param string $value            
     * @return \exface\Core\Interfaces\Widgets\iShowDataColumn
     */
    public function setDataColumnName($value);
}