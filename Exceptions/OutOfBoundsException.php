<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown if a value is not a valid key. This represents errors that cannot be detected at compile time.
 * 
 * Typically this could be used in code that attempts to access an associative array, but performs a check for the key.
 * Also, another use for this can be when you implement ArrayAccess in your class.
 * 
 * @see OutOfRangeException
 * 
 * @author Andrej Kabachnik
 *
 */
class OutOfBoundsException extends \OutOfBoundsException implements ErrorExceptionInterface, \Throwable {
	
	use ExceptionTrait;
	
}
?>