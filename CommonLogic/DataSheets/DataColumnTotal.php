<?php namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Exceptions\DataSheetException;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;

class DataColumnTotal implements iCanBeConvertedToUxon, ExfaceClassInterface {
	const FUNCTION_SUM = 'SUM';
	const FUNCTION_AVG = 'AVG';
	const FUNCTION_MIN = 'MIN';
	const FUNCTION_MAX = 'MAX';
	
	private $function = null;
	private $data_column = null;
	
	function __construct(DataColumnInterface &$column, $function_name = null){
		$this->set_column($column);
		if (!is_null($function_name)){
			$this->set_function($function_name);
		}
	}
	
	/**
	 * @return DataColumn
	 */
	public function get_column() {
		return $this->data_column;
	}
	
	public function set_column(DataColumnInterface &$value) {
		$this->data_column = $value;
		return $this;
	} 
	
	public function get_function() {
		return $this->function;
	}
	
	public function set_function($value) {
		if (!defined('self::FUNCTION_' . $value)){
			throw new DataSheetException('Cannot set totals function "' . $value . '" for data column "' . $this->get_column()->get_name() . '": invalid function!');
		}
		$this->function = $value;
		return $this;
	}  
	
	public function export_uxon_object(){
		$uxon = $this->get_column()->get_data_sheet()->get_workbench()->create_uxon_object();
		$uxon->set_property('function', $this->get_function());
		return $uxon;
	}
	
	public function import_uxon_object (UxonObject $uxon){
		$this->set_function($uxon->get_property('function'));
	}
	
	public function get_workbench(){
		return $this->get_column()->get_workbench();
	}
}