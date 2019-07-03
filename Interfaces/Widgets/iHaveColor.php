<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

/**
 * This interface describes widgets, that have a main color: Text, ChartSeries, Tiles, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iHaveColor extends WidgetInterface
{
    /**
     * Returns the color of this widget or NULL if no color explicitly defined.
     * 
     * @return string|null
     */
    public function getColor() : ?string;

    /**
     * Sets a specific color for the widget - if not set, facades will use their own color scheme.
     * 
     * HTML color names are supported by default. Additionally any color selector supported by
     * the current facade can be used. Most HTML facades will support css colors.
     * 
     * @link https://www.w3schools.com/colors/colors_groups.asp
     * 
     * @param string $color
     * @return iHaveColor
     */
    public function setColor($color);
}