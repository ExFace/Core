<?php
namespace exface\Core\Interfaces\Widgets;

/**
 * This interface is meant for container widgets, which take care of positioning their contents according
 * to certaine layout rules: e.g.
 * the panel.
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
     * @return integer
     */
    public function getNumberOfColumns();

    /**
     * Set the number of columns in the layout. The default depends on the template.
     *
     * @param integer $value
     * @return iLayoutWidgets            
     */
    public function setNumberOfColumns($value);
}