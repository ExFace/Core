<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * Interface for widgets, widget parts, etc., that support the visibility property.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iHaveVisibility
{
    /**
     * Returns the current visibility option (one of the EXF_WIDGET_VISIBILITY_xxx constants)
     *
     * @return integer
     */
    public function getVisibility() : int;
    
    /**
     * Sets visibility of the widget.
     *
     * Accepted values are either one of the EXF_WIDGET_VISIBILITY_xxx or the
     * the "xxx" part of the constant name as string: e.g. "normal", "promoted".
     *
     * @param string|int $value
     * @throws WidgetPropertyInvalidValueError
     */
    public function setVisibility($value) : iHaveVisibility;
}