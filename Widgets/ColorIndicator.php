<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\DataTypes\BooleanDataType;

/**
 * A ColorIndicator will change it's color depending the value of it's attribute.
 * 
 * Colors can be defined as a simple color scale (like in many other display widgets).
 * 
 * ## Examples
 * 
 * ### Simple color scale
 * 
 * ```
 * {
 *  "widget_type": "ColorIndicator",
 *  "color_scale": {
 *      "0": "red",
 *      "50": "yellow",
 *      "100": "green"
 *  }
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class ColorIndicator extends Display implements iHaveColor
{
    private $fixedColor = null;
    
    private $fill = true;
    
    private $colorOnly = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::getColor()
     */
    public function getColor($value = null) : ?string
    {
        return $this->fixedColor ?? parent::getColor($value);
    }

    /**
     * Use this fixed color
     * 
     * @uxon-property color
     * @uxon-type color
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::setColor()
     */
    public function setColor($color)
    {
        $this->fixedColor = $color;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getFill()
    {
        return $this->fill;
    }

    /**
     * Set to FALSE to only color the value of the widget instead of filling it with color.
     * 
     * @uxon-property fill
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param boolean $trueOrFalse
     * @return ColorIndicator
     */
    public function setFill($trueOrFalse)
    {
        $this->fill = BooleanDataType::cast($trueOrFalse);
        return $this;
    }

    /**
     * 
     * @param bool $default
     * @return bool
     */
    public function getColorOnly(bool $default = false) : bool
    {
        return $this->colorOnly ?? $default;
    }
    
    /**
     * Set to TRUE/FALSE to display only the color or to color the value respecitvely.
     * 
     * The default depends on the facade used.
     * 
     * @uxon-property color_only
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return ColorIndicator
     */
    public function setColorOnly(bool $value) : ColorIndicator
    {
        $this->colorOnly = $value;
        return $this;
    }    
}