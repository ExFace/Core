<?php namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\DataSources\DataSheetExceptionInterface;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\DataSheets\DataSheetExceptionTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

class DataSheetRuntimeError extends RuntimeException implements DataSheetExceptionInterface, ErrorExceptionInterface {
	
	use DataSheetExceptionTrait;
	
	public function __construct (DataSheetInterface $data_sheet, $message, $code = null, $previous = null) {
		parent::__construct($message, ($code ? $code : static::get_default_code()), $previous);
		$this->set_data_sheet($data_sheet);
	}
	
}
?>