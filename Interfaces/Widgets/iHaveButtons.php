<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\Widgets\Button;

interface iHaveButtons extends iHaveChildren {
	
	/**
	 * Adds a button to the widget
	 * 
	 * @param \exface\Core\Widgets\Button $button_widget
	 */
	public function add_button(Button $button_widget);
	
	/**
	 * Removes a button from the widget
	 * 
	 * @param Button $button_widget
	 */
	public function remove_button(Button $button_widget);
	
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
	
	/**
	 * Returns the widget type to be used for buttons in this widget. Regular forms use ordinary buttons, but Dialogs 
	 * use special DialogButtons capable of closing the Dialog, Data widgets use DataButtons, that can be bound to
	 * mouse clicks on the data, etc. This special getter function allows all the logic to be inherited while just 
	 * replacing the button class.
	 *
	 * @return string
	 */
	public function get_button_widget_type();
	  
}