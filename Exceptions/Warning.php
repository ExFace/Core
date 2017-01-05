<?php namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\WarningExceptionInterface;

class Warning extends \Exception implements WarningExceptionInterface, \Throwable {
	
	use ExceptionTrait;
	
}