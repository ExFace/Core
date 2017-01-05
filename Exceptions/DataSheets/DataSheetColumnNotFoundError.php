<?php namespace exface\Core\Exceptions\DataSheets;

/**
 * Exception thrown if a requested column cannot be found in the data sheet.
 *
 * @author Andrej Kabachnik
 *
 */
class DataSheetColumnNotFoundError extends DataSheetRuntimeError {
	
	public static function get_default_alias(){
		return '6T5V6WE';
	}
}
?>