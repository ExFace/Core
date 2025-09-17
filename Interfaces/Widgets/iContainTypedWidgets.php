<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

/**
 * Interface for container widgets, that only allow certain widget types as direct children
 *
 * @author Andrej Kabachnik
 */
interface iContainTypedWidgets extends iContainOtherWidgets
{
    /**
     * @param WidgetInterface $widget
     * @return bool
     */
    public function isWidgetAllowed(WidgetInterface $widget) : bool;

    /**
     * @param string $typeOrClassOrInterface
     * @return bool
     */
    public function isWidgetTypeAllowed(string $typeOrClassOrInterface) : bool;
}