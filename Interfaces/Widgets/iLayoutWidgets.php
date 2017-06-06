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

    /**
     * Returns TRUE if the columns should be stacked on small screens and FALSE otherwise.
     * 
     * Returns NULL if the creator of the widget had made no preference and 
     * thus the stacking is completely upto the template.
     *
     * @return boolean
     */
    public function getStackColumnsOnTabletsSmartphones();

    /**
     * Determines wether columns should be stacked on smaller screens (TRUE) or left side-by-side (FALSE).
     * Setting this to NULL will
     * leave it upto the template to decide.
     *
     * @param boolean $value 
     * @return iLayoutWidgets              
     */
    public function setStackColumnsOnTabletsSmartphones($value);

    /**
     * Returns TRUE if the columns should be stacked on midsize screens and FALSE otherwise.
     * Returns NULL if the creator of the widget
     * had made no preference and thus the stacking is completely upto the template.
     *
     * @return boolean
     */
    public function getStackColumnsOnTabletsTablets();

    /**
     * Determines wether columns should be stacked on midsize screens (TRUE) or left side-by-side (FALSE).
     * Setting this to NULL will
     * leave it upto the template to decide.
     *
     * @param boolean $value
     * @return iLayoutWidgets            
     */
    public function setStackColumnsOnTabletsTablets($value);
}