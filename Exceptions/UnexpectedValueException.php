<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class UnexpectedValueException extends \UnexpectedValueException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>