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
	 * Returns the widget with the given id from this page or FALSE if no matching widget could be found. The search
	 * can optionally be restricted to the children of another widget.
	 *
	 * @param string $widget_id
	 * @param WidgetInterface $parent
	 * @return WidgetInterface|null
	 */
	public function get_widget($widget_id, WidgetInterface $parent = null);
	
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
	 * Removes the widget with the given id from this page. This will not remove child widgets!
	 * 
	 * @see remove_widget() for a more convenient alternative optionally removing children too.
	 *
	 * @param string $widget_id
	 * @param boolean $remove_children_too
	 * @return \exface\Core\CommonLogic\UiPage
	 */
	public function remove_widget_by_id($widget_id);
	
	/**
	 * Removes a widget from the page. By default all children are removed too.
	 * 
	 * Note, that if the widget has a parent and that parent still is on this page, the widget 
	 * will merely be removed from cache, but will still be accessible through page::get_widget().
	 *
	 * @param WidgetInterface $widget
	 * @return UiPageInterface
	 */
	public function remove_widget(WidgetInterface $widget, $remove_children_too = true);
	
	/**
	 * @return UiManagerInterface
	 */
	public function get_ui();
	
	/**
	 * @return string
	 */
	public function get_widget_id_separator();
	
	/**
	 * @return string
	 */
	public function get_widget_id_space_separator();
	
	/**
	 * Returns TRUE if the page does not have widgets and FALSE if there is at least one widget.
	 * 
	 * @return boolean
	 */
	public function is_empty();
}

?>
