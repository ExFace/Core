<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception that represents error in the program logic. This kind of exception should lead directly to a fix in your code.
 * 
 * The main use for LogicException is a bit similar to DomainException – it should be used if your code (for example a calculation) 
 * produces a value that it shouldn’t produce. 
 * 
 * @author Andrej Kabachnik
 *
 */
class LogicException extends \LogicException implements ErrorExceptionInterface, \Throwable {
	
	use ExceptionTrait;
	
}
?>