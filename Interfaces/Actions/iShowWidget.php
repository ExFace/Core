<?php namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;

interface iShowWidget extends iNavigate {
	
	/**
	 * @return WidgetInterface
	 */
	public function get_widget();
	
	/**
	 * 
	 * @param WidgetInterface|UxonObject|string $any_widget_source
	 */
	public function set_widget($any_widget_source);
	
	/**
	 * The output of an action showing a widget is the widget instance
	 * @return WidgetInterface
	 */
	public function get_result();
}