<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Data;
use exface\Core\CommonLogic\WidgetLink;
use exface\Core\CommonLogic\UxonObject;

interface iShowDataSet extends WidgetInterface {
	
	/**
	 * @return Data
	 */
	public function get_data();
	
	/**
	 * 
	 * @param \stdClass $uxon_object
	 * @return \exface\Core\Interfaces\Widgets\iShowDataColumn
	 */
	public function set_data(\stdClass $uxon_object);
	
	/**
	 *
	 * @return WidgetLink
	 */
	public function get_data_widget_link();
	
	/**
	 * 
	 * @param string|UxonObject|WidgetLink $string_or_uxon_or_widget_link
	 */
	public function set_data_widget_link($string_or_uxon_or_widget_link);
	  
}