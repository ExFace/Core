<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown if a length is invalid. It represents errors, that should be detected at compile time.
 * 
 * A LengthException can be used when the length of something is too short or too long – For example, 
 * a file name’s length could be too long. This can also be applied if an array’s length is incorrect.
 * 
 * @see LogicException
 * 
 * @author Andrej Kabachnik
 *
 */
class LengthException extends \LengthException implements ErrorExceptionInterface, \Throwable {
	
	use ExceptionTrait;
	
}
?>