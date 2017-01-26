<?php
namespace exface\Core\Interfaces\Widgets;
interface iHaveChildren {
	
	/**
	 * Returns all direct children of the current widget or an empty array, if the widget has no children
	 * @return WidgetInterface[]
	 */
	public function get_children();
	
	/**
	 * Returns all children of the current widget including with their children, childrens children, etc. as a flat array of widgets
	 * @return WidgetInterface[]
	 */
	public function get_children_recursive();
	
}