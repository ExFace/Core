<?php namespace exface\Core\Interfaces\Widgets;

/**
 * Action-widgets (e.g. Buttons), that implement this interface do not communicate with the server every time, when triggered. If staged writing 
 * is enabled, the templates will buffer data for this action in the client and send it to the server with another request (when - depends on 
 * the template implementation). Thus, a CreateData button in a pop-up editor within a form with enabled staged writing will not write to the 
 * data source directly, but will save data in the form on client side and will send it to the server once the form is submitted.
 * 
 * If the template does not support asynchronous requests, staged writing will do exactly the same as normal writing.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iSupportStagedWriting extends iTriggerAction {
	
	/**
	 * Returns TRUE if staged writing is enabled for this action and FALSE otherwise
	 * @return boolean
	 */
	public function is_staged_writing_enabled();
	
	/**
	 * Returns the current value of the stage writing property
	 * @return boolean
	 */
	public function get_staged_writing();
	
	/**
	 * Enables (TRUE) or disables (FALSE) for this action
	 * @param boolean $value
	 */
	public function set_staged_writing($true_or_false);
	
}