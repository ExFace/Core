<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown if an error which can only be found on runtime occurs.
 * 
 * @author Andrej Kabachnik
 *
 */
class RuntimeException extends \RuntimeException implements ErrorExceptionInterface {
	
	use ExceptionTrait;
	
}
?>