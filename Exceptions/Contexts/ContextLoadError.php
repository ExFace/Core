<?php namespace exface\Core\Exceptions\Contexts;

/**
 * Exception thrown if a context fails to load data from the respective scope.
 * 
 * @author Andrej Kabachnik
 *
 */
class ContextLoadError extends ContextRuntimeError {
	
	public static function get_default_code(){
		return '6T5E400';
	}
	
}