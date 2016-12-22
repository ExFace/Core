<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

/**
 * Exception thrown if a callback refers to an undefined method or if some arguments are missing.
 *
 * @author Andrej Kabachnik
 *
 */
class DomainException extends \DomainException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>