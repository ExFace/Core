<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class InvalidArgumentException extends \BadMethodCallException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>