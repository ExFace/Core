<?php
namespace exface\Core\Interfaces\Widgets;
interface iCollapsible {
	
	/**
	 * Returs TRUE if the widget is collapsible, FALSE otherwise
	 * @return boolean
	 */
	public function get_collapsible();
	
	/**
	 * Defines if widget shall be collapsible (TRUE) or not (FALSE)
	 * @param boolean $value
	 * @return boolean
	 */
	public function set_collapsible($value);
	  
}