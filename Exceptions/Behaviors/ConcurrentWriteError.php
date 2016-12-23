<?php namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\DataSheets\DataSheetWriteError;

class ConcurrentWriteError extends DataSheetWriteError {
	public static function get_default_code(){
		return '6T6HZLF';
	}
}
