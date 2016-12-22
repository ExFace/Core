<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown when performing an invalid operation on an empty container, such as removing an element.
 * 
 * @author Andrej Kabachnik
 *
 */
class UnderflowException extends \UnderflowException implements ErrorExceptionInterface {
	
	use ExceptionTrait;
	
}
?>