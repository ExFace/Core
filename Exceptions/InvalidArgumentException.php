<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>