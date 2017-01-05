<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown when adding an element to a full container.
 * 	
 * @author Andrej Kabachnik
 *
 */
class OverflowException extends \OverflowException implements ErrorExceptionInterface, \Throwable {
	
	use ExceptionTrait;
	
}
?>