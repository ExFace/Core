<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iHaveColor;

/**
 * This trait contains methods to work with value-based color scales.
 *
 * @author Andrej Kabachnik
 *
 */
trait iHaveColorTrait
{
    private $color = null;
    
    /**
     *
     * @return string
     */
    public function getColor() : ?string
    {
        return $this->color;
    }
    
    /**
     * Changes the color of the widget to any HTML color value (or other facade-specific value)
     *
     * @uxon-property color
     * @uxon-type color|string
     *
     * @param string $color
     * @return \exface\Core\Widgets\Tile
     */
    public function setColor($color) : iHaveColor
    {
        $this->color = $color;
        return $this;
    }
}