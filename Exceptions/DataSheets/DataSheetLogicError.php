<?php namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\DataSheets\DataSheetExceptionTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\Exceptions\DataSheetExceptionInterface;

/**
 * This exception should be used in data sheets instead of the regular LogicException in order to provied more
 * detailed debug information like the data sheet contents, etc..
 * 
 * @see LogicException
 *
 * @author Andrej Kabachnik
 *
 */
class DataSheetLogicError extends LogicException implements DataSheetExceptionInterface, ErrorExceptionInterface {
	
	use DataSheetExceptionTrait;
	
	/**
	 * 
	 * @param DataSheetInterface $data_sheet
	 * @param string $message
	 * @param string $alias
	 * @param \Throwable $previous
	 */
	public function __construct (DataSheetInterface $data_sheet, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_data_sheet($data_sheet);
	}
	
}
?>