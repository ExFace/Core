<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown if an argument is not of the expected type.
 * 
 * @author Andrej Kabachnik
 *
 */
class InvalidArgumentException extends \InvalidArgumentException implements ErrorExceptionInterface, \Throwable {
	
	use ExceptionTrait;
	
}
?>