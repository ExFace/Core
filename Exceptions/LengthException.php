<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown if a length is invalid.
 * 
 * @author Andrej Kabachnik
 *
 */
class LengthException extends \LengthException implements ErrorExceptionInterface {
	
	use ExceptionTrait;
	
}
?>