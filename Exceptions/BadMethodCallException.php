<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown if a callback refers to an undefined method or if some arguments are missing.
 *
 * @author Andrej Kabachnik
 *
 */
class BadMethodCallException extends \BadMethodCallException implements ErrorExceptionInterface, \Throwable {
	
	use ExceptionTrait;
	
}
?>