<?php namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Exceptions\DomainException;
use exface\Core\Exceptions\DataSheets\DataSheetRuntimeError;
use exface\Core\Exceptions\DataSheets\DataSheetStructureError;

class DataColumnTotal implements iCanBeConvertedToUxon, ExfaceClassInterface {
	
	private $function = null;
	private $data_column = null;
	
	function __construct(DataColumnInterface $column, $function_name = null){
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
	
	public function set_column(DataColumnInterface $column_instance) {
		if (!$column_instance->get_attribute()){
			throw new DataSheetStructureError($column_instance->get_data_sheet(), 'Cannot add a total to column "' . $column_instance->get_name() . '": this column does not represent a meta attribute!', '6UQBUVZ');
		}
		$this->data_column = $column_instance;
		return $this;
	} 
	
	public function get_function() {
		return $this->function;
	}
	
	public function set_function($value) {
		if (!defined('EXF_AGGREGATOR_' . $value)){
			throw new DomainException('Cannot set totals function "' . $value . '" for data column "' . $this->get_column()->get_name() . '": invalid function!', '6T5UXLD');
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