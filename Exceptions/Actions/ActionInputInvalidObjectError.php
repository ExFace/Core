<?php namespace exface\Core\Exceptions\Actions;

/**
 * Exception thrown if an action receives an input data sheet based on a meta object, that the action can't deal with.
 * 
 * Most (none-core) actions make sense only for specific meta objects. These actions should check the input data sheet
 * and throw this exception, if it does not contain a suitable object.
 *
 * @author Andrej Kabachnik
 *
 */
class ActionInputInvalidObjectError extends ActionInputError {
	
	public static function get_default_alias(){
		return '6T5DMUS';
	}
	
}
