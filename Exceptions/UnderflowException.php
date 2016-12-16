<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class InvalidArgumentException extends \UnderflowException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>