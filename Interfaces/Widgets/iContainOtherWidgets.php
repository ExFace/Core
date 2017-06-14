<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Widgets\iHaveChildren;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\AbstractWidget;

interface iContainOtherWidgets extends iHaveChildren
{

    /**
     *
     * @param AbstractWidget $widget            
     * @param integer $position            
     * @return iContainOtherWidgets
     */
    public function addWidget(AbstractWidget $widget, $position = NULL);

    /**
     *
     * @param AbstractWidget[] $widgets            
     */
    public function addWidgets(array $widgets);

    /**
     * Returns all widgets in this container as an array
     *
     * @return WidgetInterface[]
     */
    public function getWidgets();

    /**
     * Returns all widgets in this container and subcontainers, that take user input.
     *
     * By default all input widgets are collected recursively from all subcontainers, but the recursion depth can be restricted
     * via $depth: e.g. get_input_widgets(1) will return only the direct children of the container.
     *
     * @param integer $depth            
     * @return WidgetInterface[]
     */
    public function getInputWidgets($depth = null);

    /**
     * Removes all widgets from the container
     *
     * @return iContainOtherWidgets
     */
    public function removeWidgets();

    /**
     * Alias for add_widgets()
     *
     * @see add_widgets()
     * @param
     *            WidgetInterface[]|UxonObject[]
     * @return iContainOtherWidgets
     */
    public function setWidgets(array $widget_or_uxon_array);

    /**
     * Returns the current number of child widgets
     *
     * @return int
     */
    public function countWidgets();

    /**
     * Returns the number of visible child widgets
     *
     * @return int
     */
    public function countVisibleWidgets();
    
    /**
     * Returns an array of direct children, that show the given attribute.
     * The array will contain only widgets implementing the interface
     * iShowSingleAttribute.
     *
     * @param Attribute $attribute            
     * @return WidgetInterface[]
     */
    public function findChildrenByAttribute(Attribute $attribute);
}