<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

/**
 * Exception thrown if a length is invalid.
 * 
 * @author Andrej Kabachnik
 *
 */
class LengthException extends \LengthException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>