<?php namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\ExfaceClassInterface;

interface EventInterface extends ExfaceClassInterface {
	
	/**
	 * Returns the events name (like BeforeQuery)
	 * @return string
	 */
	public function get_name();
	
	/**
	 * Sets the events name
	 * @param string $value
	 */
	public function set_name($value);
	
	/**
	 * Returns the events fully qualified name (like exface.SqlDataConnector.DataConnection.BeforeQuery)
	 * @return string
	 */
	public function get_name_with_namespace();
	
	/**
	 * Returns the events namespace (typicall constistant of the app namespace and some kind of event specific suffix)
	 * @return string
	 */
	public function get_namespace();
	
	/**
	 * Prevents propagation of this event to further listeners
	 * @return void
	 */
	public function stop_propagation();
	
	/**
	 * Returns TRUE if no further listeners will be triggerd by this event or FALSE otherwise
	 * @return boolean
	 */
	public function is_propagation_stopped();
	
}
?>