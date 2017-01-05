<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\Widgets\Button;

interface iHaveButtons extends iHaveChildren {
	
	/**
	 * Adds a button to the widget
	 * 
	 * @param \exface\Core\Widgets\Button $button_widget
	 */
	public function add_button($button_widget);
	
	/**
	 * Removes a button from the widget
	 * 
	 * @param Button $button_widget
	 */
	public function remove_button($button_widget);
	
	/**
	 * Returs an array of button widgets
	 * @return Button[]
	 */
	public function get_buttons();
	
	/**
	 * Adds multiple buttons from an array of their UXON descriptions
	 * @param array $buttons_array of UXON descriptions for buttons
	 * @return boolean
	 */
	public function set_buttons(array $buttons);
	
	/**
	 * @return boolean
	 */
	public function has_buttons();
	  
}