<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class InvalidArgumentException extends \OutOfRangeException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>