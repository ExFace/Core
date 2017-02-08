<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Widgets\iHaveChildren;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\AbstractWidget;

interface iContainOtherWidgets extends iHaveChildren {
	
	/**
	 * 
	 * @param AbstractWidget $widget
	 * @param integer $position
	 * @return iContainOtherWidgets
	 */
	public function add_widget(AbstractWidget $widget, $position = NULL);

	/**
	 *
	 * @param AbstractWidget[] $widgets
	 */
	public function add_widgets(array $widgets);

	/**
	 * Returns all widgets in this container as an array
	 * @return WidgetInterface[]
	 */
	public function get_widgets();
	
	/**
	 * Returns all widgets in this container and subcontainers, that take user input.
	 * 
	 * By default all input widgets are collected recursively from all subcontainers, but the recursion depth can be restricted 
	 * via $depth: e.g. get_input_widgets(1) will return only the direct children of the container.
	 * 
	 * @param integer $depth
	 * @return WidgetInterface[]
	 */
	public function get_input_widgets($depth = null);
	
	/**
	 * Removes all widgets from the container
	 * @return iContainOtherWidgets
	 */
	public function remove_widgets();
	
	/**
	 * Alias for add_widgets()
	 * @see add_widgets()
	 * @param WidgetInterface[]|UxonObject[]
	 * @return iContainOtherWidgets
	 */
	public function set_widgets(array $widget_or_uxon_array);

	/**
	 * Returns the current number of child widgets
	 * @return int
	 */
	public function count_widgets();

	/**
	 * Returns an array of direct children, that show the given attribute. The array will contain only widgets implementing the interface 
	 * iShowSingleAttribute. 
	 *  
	 * @param Attribute $attribute
	 * @return WidgetInterface[]
	 */
	public function find_children_by_attribute(Attribute $attribute);
	
}