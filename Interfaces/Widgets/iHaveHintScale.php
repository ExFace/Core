<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

/**
 * This interface marks a widget as being able to change its hint based on the current value (similarly to iHaveColorScale)
 * 
 * @author Andrej Kabachnik
 *
 */
interface iHaveHintScale extends WidgetInterface
{
    /**
     * 
     * @return WidgetPropertyScaleInterface
     */
    public function getHintScale() : WidgetPropertyScaleInterface;
}