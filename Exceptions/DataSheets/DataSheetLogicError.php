<?php namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\DataSheets\DataSheetExceptionTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\Exceptions\DataSheetExceptionInterface;

class DataSheetLogicError extends LogicException implements DataSheetExceptionInterface, ErrorExceptionInterface {
	
	use DataSheetExceptionTrait;
	
	/**
	 * 
	 * @param DataSheetInterface $data_sheet
	 * @param string $message
	 * @param string $code
	 * @param string $previous
	 */
	public function __construct (DataSheetInterface $data_sheet, $message, $code = null, $previous = null) {
		parent::__construct($message, ($code ? $code : static::get_default_code()), $previous);
		$this->set_data_sheet($data_sheet);
	}
	
}
?>