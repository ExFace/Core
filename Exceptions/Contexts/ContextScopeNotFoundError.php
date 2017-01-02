<?php namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\OutOfRangeException;

class ContextScopeNotFoundError extends OutOfRangeException implements ErrorExceptionInterface {
	
	public static function get_default_alias(){
		return '6T5E14B';
	}
	
}