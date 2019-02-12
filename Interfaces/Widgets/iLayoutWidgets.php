<?php
namespace exface\Core\Interfaces\Widgets;

/**
 * This interface defines a container, that takes care of positioning their contents according
 * to certaine layout rules - mostly in a grid: e.g. the WidgetGrid, the Panel, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
interface iLayoutWidgets extends iContainOtherWidgets
{
    /**
     * Returns the number of columns in the layout.
     * 
     * Returns NULL if the creator of the widget had made no preference and 
     * thus the number of columns is completely upto the template. 
     *
     * @return int|NULL
     */
    public function getColumnsInGrid() : ?int;

    /**
     * Set the number of columns in the layout. The default depends on the template.
     *
     * @param int $value
     * @return iLayoutWidgets            
     */
    public function setColumnsInGrid(int $value) : iLayoutWidgets;
}