<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface iShowDataColumn extends WidgetInterface {
	
	/**
	 * @return string
	 */
	public function get_data_column_name();
	
	/**
	 * 
	 * @param string $value
	 * @return \exface\Core\Interfaces\Widgets\iShowDataColumn
	 */
	public function set_data_column_name($value);	
	  
}