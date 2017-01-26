<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown when an illegal index was requested. This represents errors that should be detected at compile time.
 * 
 * @see LogicException
 * @see OutOfBoundsException for the respective runtime exception
 * 
 * @author Andrej Kabachnik
 *
 */
class OutOfRangeException extends \OutOfRangeException implements ErrorExceptionInterface, \Throwable {
	
	use ExceptionTrait;
	
}
?>