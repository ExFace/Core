<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class InvalidArgumentException extends \RangeException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>