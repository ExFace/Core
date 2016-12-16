<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class InvalidArgumentException extends \OverflowException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>