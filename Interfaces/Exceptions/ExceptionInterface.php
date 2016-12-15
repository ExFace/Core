<?php namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\iCanBeConvertedToUxon;

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
}
