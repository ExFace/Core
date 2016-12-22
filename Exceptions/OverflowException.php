<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

/**
 * Exception thrown when adding an element to a full container.
 * 	
 * @author Andrej Kabachnik
 *
 */
class OverflowException extends \OverflowException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>