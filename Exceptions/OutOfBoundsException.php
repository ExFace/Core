<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class InvalidArgumentException extends \OutOfBoundsException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>