<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Widgets\iHaveChildren;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\Exceptions\Widgets\WidgetChildNotFoundError;
use exface\Core\Exceptions\UnderflowException;

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
     * Returns all widgets in this container as an array optionally filterd via given closure
     *
     * @param callable $filter
     * 
     * @return WidgetInterface[]
     */
    public function getWidgets(callable $filter = null);
    
    /**
     * Returns the first widget matching the filter or the first one overall 
     * if no filter is defined.
     * 
     * @param callable $filter
     * 
     * @throws UnderflowException if the container is empty or no widget matches the filter
     * 
     * @return WidgetInterface
     */
    public function getWidgetFirst(callable $filter = null);
    
    /**
     * Returns the N-th widget from the container (specified by the index 
     * starting with 0).
     * 
     * @param integer $index
     * @throws WidgetChildNotFoundError if the index cannot be found.
     * @return WidgetInterface
     */
    public function getWidget($index);
    
    /**
     * Returns the index (= position starting with 0) of the given widget in the 
     * container or FALSE if the widget cannot be found.
     * 
     * @param WidgetInterface $widget
     * 
     * @return integer|boolean
     */
    public function getWidgetIndex(WidgetInterface $widget);

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
     * Removes the given widget from the container.
     * 
     * NOTE: the widget is neither destroyed nor changes it's parent, but it
     * will not be returned by getWidgets() of the container anymore.
     * 
     * @param WidgetInterface $widget
     * @return iContainOtherWidgets
     */
    public function removeWidget(WidgetInterface $widget);

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
     * Returns the current number of child widgets optionally filtering them via given closure.
     *
     * @param callable $filter
     * 
     * @return int
     */
    public function countWidgets(callable $filter = null);
    /**
     * Returns TRUE if the container has at least one widget and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasWidgets();
    
    /**
     * Returns TRUE if the container is empty and FALSE otherwise.
     * 
     * In contrast to hasWidgets() this will return FALSE even if there are
     * widgets within this container, but they are part of it's structure. Thus,
     * a Tabs widget is empty if neither tab has widgets, while hasWidgets()
     * would return TRUE as soon as at least one tab is created.
     *
     * @return boolean
     */
    public function isEmpty();

    /**
     * Returns an array of direct children, that show the given attribute.
     * The array will contain only widgets implementing the interface
     * iShowSingleAttribute.
     *
     * @param Attribute $attribute            
     * @return WidgetInterface[]
     */
    public function findChildrenByAttribute(Attribute $attribute);
    
    /**
     * Returns the direct child widget with the given id or boolean FALSE if there is no matching child.
     *
     * @param string $widget_id
     * @return WidgetInterface|boolean
     */
    public function findChildById($widget_id);
}