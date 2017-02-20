<?php
namespace exface\Core\Interfaces\Widgets;
use exface\Core\Interfaces\WidgetInterface;

interface iHaveIcon extends WidgetInterface {
	
	/**
	 * Returs the name of the icon to be used
	 * @return string
	 */
	public function get_icon_name();
	
	/**
	 * If set, the widget will display the defined icon (if the template supports it, of course) 
	 * @param string $value
	 * @return boolean
	 */
	public function set_icon_name($value);
	  
}