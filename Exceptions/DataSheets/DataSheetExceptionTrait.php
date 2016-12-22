<?php namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\ExceptionTrait;

trait DataSheetExceptionTrait {
	
	use ExceptionTrait {
		create_widget as create_parent_widget;
	}
	
	private $data_sheet = null;
	
	public function __construct (DataSheetInterface $data_sheet, $message, $code = null, $previous = null) {
		parent::__construct($message, ($code ? $code : static::get_default_code()), $previous);
		$this->set_data_sheet($data_sheet);
	}
	
	public function get_data_sheet(){
		return $this->data_sheet;
	}
	
	public function set_data_sheet(DataSheetInterface $sheet){
		$this->data_sheet = $sheet;
		return $this;
	}
	
}
?>