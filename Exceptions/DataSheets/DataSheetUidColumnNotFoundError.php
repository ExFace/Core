<?php namespace exface\Core\Exceptions\DataSheets;

/**
 * Exception thrown the UID column of a data sheet cannot be found while being required for the current operation.
 *
 * @author Andrej Kabachnik
 *
 */
class DataSheetUidColumnNotFoundError extends DataSheetRuntimeError {
	
	public static function get_default_alias(){
		return '6T5V2Q8';
	}
}
?>