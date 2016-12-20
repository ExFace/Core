<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

trait DataSheetExceptionTrait {
	
	private $data_sheet = null;
	
	public function __construct (DataSheetInterface $data_sheet, $message, $code, $previous = null) {
		parent::__construct($message, $code, $previous);
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