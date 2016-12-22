<?php namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\UiPageInterface;
use exface\Core\Widgets\ErrorMessage;

interface ExceptionInterface extends iCanBeConvertedToUxon {
	/**
	 * Returns TRUE if this exception is a warning and FALSE otherwise
	 * @return boolean
	 */
	public function is_warning();
	
	/**
	 * Returns TRUE if this exception is an error and FALSE otherwise
	 * @return boolean
	 */
	public function is_error();
	
	/**
	 * Creates a widget with detailed information about this exception. 
	 * 
	 * @param UiPageInterface $page
	 * @return ErrorMessage
	 */
	public function create_widget(UiPageInterface $page);
	
	/**
	 * Returns the default error code for this type of exception. If no error code is given in the constructor, the default
	 * will be used to generate a link to the help, etc.
	 * 
	 * @return string
	 */
	public static function get_default_code();
}
