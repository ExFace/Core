<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class InvalidArgumentException extends \DomainException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>