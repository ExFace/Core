<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class InvalidArgumentException extends \LengthException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>