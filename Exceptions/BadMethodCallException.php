<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class BadMethodCallException extends \BadMethodCallException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>