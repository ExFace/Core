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
     * Returns the number of columns in the layout
     *
     * @return integer
     */
    public function getColumnNumber();

    /**
     * Set the number of columns in the layout
     *
     * @param integer $value            
     */
    public function setColumnNumber($value);

    /**
     * Returns TRUE if the columns should be stacked on small screens and FALSE otherwise.
     * Returns NULL if the creator of the widget
     * had made no preference and thus the stacking is completely upto the template.
     *
     * @return boolean
     */
    public function getColumnStackOnSmartphones();

    /**
     * Determines wether columns should be stacked on smaller screens (TRUE) or left side-by-side (FALSE).
     * Setting this to NULL will
     * leave it upto the template to decide.
     *
     * @param boolean $value            
     */
    public function setColumnStackOnSmartphones($value);

    /**
     * Returns TRUE if the columns should be stacked on midsize screens and FALSE otherwise.
     * Returns NULL if the creator of the widget
     * had made no preference and thus the stacking is completely upto the template.
     *
     * @return boolean
     */
    public function getColumnStackOnTablets();

    /**
     * Determines wether columns should be stacked on midsize screens (TRUE) or left side-by-side (FALSE).
     * Setting this to NULL will
     * leave it upto the template to decide.
     *
     * @param boolean $value            
     */
    public function setColumnStackOnTablets($value);
}