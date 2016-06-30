<?php
namespace exface\Core\Interfaces\Widgets;
interface iAmResizable {
	
	/**
	 * Returs TRUE if the widget is resizable, FALSE otherwise
	 * @return boolean
	 */
	public function get_resizable();
	
	/**
	 * Defines if widget shall be resizable (TRUE) or not (FALSE)
	 * @param boolean $value
	 * @return boolean
	 */
	public function set_resizable($value);
	  
}