<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\UiPageInterface;
use exface\Core\CommonLogic\UxonObject;

interface WidgetLinkInterface extends ExfaceClassInterface, iCanBeConvertedToUxon {
	
	public function parse_link($string_or_object);
	
	/**
	 * Parse expressions like [page_id]widget_id!column$row
	 */
	public function parse_link_string($string);
	
	/**
	 * @return string
	 */
	public function get_page_id();
	
	/**
	 * @return UiPageInterface
	 */
	public function get_page();
	
	/**
	 * 
	 * @param string $value
	 * @return WidgetLinkInterface
	 */
	public function set_page_id($value);
	
	/**
	 * @return string
	 */
	public function get_widget_id();
	
	/**
	 * 
	 * @param string $value
	 * @return WidgetLinkInterface
	 */
	public function set_widget_id($value);
	
	/**
	 * Returns the widget instance referenced by this link
	 * @throws uiWidgetNotFoundException if no widget with a matching id can be found in the specified resource
	 * @return WidgetInterface
	 */
	public function get_widget(); 
	
	/**
	 * @return UxonObject
	 */
	public function get_widget_uxon();
	
	/**
	 * @return string
	 */
	public function get_column_id();
	
	/**
	 * 
	 * @param string $value
	 * @return WidgetLinkInterface
	 */
	public function set_column_id($value);
	
	/**
	 * @return integer
	 */
	public function get_row_number();
	
	/**
	 * 
	 * @param integer $value
	 * @return WidgetLinkInterface
	 */
	public function set_row_number($value);    
}
?>