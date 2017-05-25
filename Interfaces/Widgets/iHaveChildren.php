<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iHaveChildren extends WidgetInterface
{

    /**
     * Returns all direct children of the current widget or an empty array, if the widget has no children
     * 
     * @return WidgetInterface[]
     */
    public function getChildren();

    /**
     * Returns all children of the current widget including with their children, childrens children, etc.
     * as a flat array of widgets
     * 
     * @return WidgetInterface[]
     */
    public function getChildrenRecursive();
}