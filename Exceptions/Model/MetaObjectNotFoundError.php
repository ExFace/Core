<?php namespace exface\Core\Exceptions\Model;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

class MetaObjectNotFoundError extends UnexpectedValueException implements ErrorExceptionInterface {
	
}