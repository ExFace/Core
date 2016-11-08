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
	 * Returns all widgets the panel contains as an array
	 * @return WidgetInterface[]
	 */
	public function get_widgets();
	
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
	 *
	 * @param Attribute $attribute
	 * @return WidgetInterface[]
	 */
	public function get_widgets_by_attribute(Attribute $attribute);
	
}