<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

/**
 * Exception thrown if an argument is not of the expected type.
 * 
 * @author Andrej Kabachnik
 *
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>