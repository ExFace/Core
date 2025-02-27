<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\Exceptions\Widgets\WidgetChildNotFoundError;
use exface\Core\Exceptions\UnderflowException;

interface iContainOtherWidgets extends WidgetInterface
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
     * @param WidgetInterface[] $widgets            
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
     * Returns the first widget matching the filter or the first one overall if no filter is defined.
     * 
     * @param callable $filter
     * @throws UnderflowException if the container is empty or no widget matches the filter
     * @return WidgetInterface
     */
    public function getWidgetFirst(callable $filter = null) : WidgetInterface;

    /**
     * Returns the last widget matching the filter or the first one overall if no filter is defined.
     * 
     * @param callable $filter
     * @throws UnderflowException if the container is empty or no widget matches the filter
     * @return WidgetInterface
     */
    public function getWidgetLast(callable $filter = null) : WidgetInterface;
    
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
     * @param int $depth            
     * @return iTakeInput[]
     */
    public function getInputWidgets(int $depth = null) : array;
    
    /**
     * Returns inner widgets of this container and any nested containers recursively.
     * 
     * The resulting array will contain all inner widgets of this container and
     * their inner widgets too.
     * 
     * @param callable $filterCallback
     * @param int $depth
     * @return WidgetInterface[]
     */
    public function getWidgetsRecursive(callable $filterCallback = null, int $depth = null) : array;

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
     * @param WidgetInterface[]|UxonObject[]
     * @return iContainOtherWidgets
     */
    public function setWidgets($widget_or_uxon_array);

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
     * @param MetaAttributeInterface $attribute            
     * @return WidgetInterface[]
     */
    public function findChildrenByAttribute(MetaAttributeInterface $attribute);
    
    /**
     * Returns an array of child widgets matching the filter.
     * 
     * @param callable $filter
     * @return WidgetInterface[]
     */
    public function findChildrenRecursive(callable $filterCallback, $maxDepth = null) : array;
    
    /**
     * Returns the direct child widget with the given id or boolean FALSE if there is no matching child.
     *
     * @param string $widget_id
     * @return WidgetInterface|boolean
     */
    public function findChildById($widget_id);
    
    /**
     * Returns TRUE if the container is filled by a single large widget entirely.
     * 
     * This is the case if the only visible child implements the interface
     * `iFillEntireContainer`. This child widget can be retrieved via 
     * `getFillerWidget()`.
     * 
     * @see getFillerWidget()
     * 
     * @return bool
     */
    public function isFilledBySingleWidget() : bool;
    
    /**
     * Returns the widget, that fills the container entirely if there is one.
     * 
     * @return iFillEntireContainer|NULL
     */
    public function getFillerWidget() : ?iFillEntireContainer;
}