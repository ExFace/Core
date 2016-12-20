<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

class RangeException extends \RangeException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>