<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class LogicException extends \LogicException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>