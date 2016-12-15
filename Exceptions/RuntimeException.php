<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class RuntimeException extends \RuntimeException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>