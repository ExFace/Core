<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

/**
 * Exception that represents error in the program logic. This kind of exception should lead directly to a fix in your code.
 * 
 * @author Andrej Kabachnik
 *
 */
class LogicException extends \LogicException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>