<?php namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\OutOfRangeException;

/**
 * Exception thrown if the requested context scope could not be found. This will typically indicate bugs in the code.
 *
 * @author Andrej Kabachnik
 *
 */
class ContextScopeNotFoundError extends OutOfRangeException implements ErrorExceptionInterface {
	
	public static function get_default_alias(){
		return '6T5E14B';
	}
	
}