<?php namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\UxonObject;

interface UiPageInterface extends ExfaceClassInterface {
	/**
	 *
	 * @param string $widget_type
	 * @param WidgetInterface $parent_widget
	 * @param string $widget_id
	 * @return WidgetInterface
	 */
	public function create_widget($widget_type, WidgetInterface $parent_widget = null, UxonObject $uxon = null);
	
	/**
	 *
	 * @return \exface\Core\Interfaces\WidgetInterface
	 */
	public function get_widget_root();
	
	/**
	 *
	 * @param string $widget_id
	 * @return WidgetInterface
	 */
	public function get_widget($widget_id);
	
	/**
	 *
	 * @return string
	 */
	public function get_id();
	
	/**
	 *
	 * @param string $value
	 * @return \exface\Core\CommonLogic\UiPage
	 */
	public function set_id($value);
	
	/**
	 * Removes the widget with the given id from this page. By default all children are removed too.
	 *
	 * @param string $widget_id
	 * @param boolean $remove_children_too
	 * @return \exface\Core\CommonLogic\UiPage
	 */
	public function remove_widget_by_id($widget_id, $remove_children_too = true);
	
	/**
	 * Removes a widget from the page. By default all children are removed too.
	 *
	 * @param WidgetInterface $widget
	 * @return UiPageInterface
	 */
	public function remove_widget(WidgetInterface $widget, $remove_children_too = true);
	
	/**
	 * @return UiManagerInterface
	 */
	public function get_ui();
}

?>
