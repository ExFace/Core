<?php namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\DataSheets\DataSheetExceptionTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Exceptions\DataSheetExceptionInterface;

class DataSheetRuntimeError extends RuntimeException implements DataSheetExceptionInterface, ErrorExceptionInterface {
	
	use DataSheetExceptionTrait;
	
	/**
	 * 
	 * @param DataSheetInterface $data_sheet
	 * @param string $message
	 * @param string $code
	 * @param string $previous
	 */
	public function __construct (DataSheetInterface $data_sheet, $message, $code = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_data_sheet($data_sheet);
	}
	
}
?>