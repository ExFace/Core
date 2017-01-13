<?php namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;

class DataAggregator implements iCanBeConvertedToUxon {
	const AGGREGATION_SEPARATOR = ':';
	
	private $attribute_alias = null;
	private $data_sheet = null;
	
	function __construct(DataSheetInterface $data_sheet){
		$this->data_sheet = $data_sheet;
	}
	
	public function get_attribute_alias() {
		return $this->attribute_alias;
	}
	
	public function set_attribute_alias($value) {
		$this->attribute_alias = $value;
		return $this;
	}
	
	public function get_data_sheet() {
		return $this->data_sheet;
	}
	
	public function set_data_sheet(DataSheetInterface $data_sheet) {
		$this->data_sheet = $data_sheet;
		return $this;
	}
	
	public function export_uxon_object(){
		$uxon = $this->get_data_sheet()->get_workbench()->create_uxon_object();
		$uxon->set_property('attribute_alias', $this->get_attribute_alias());
		return $uxon;
	}
	
	public function import_uxon_object(UxonObject $uxon){
		$this->set_attribute_alias($uxon->get_property('attribute_alias'));
	}
	
	/**
	 * PRODUCT->SIZE:CONCAT(',') --> CONCAT(EXF_LIST_SEPARATOR)
	 * @param string $attribute_alias
	 * @return string|boolean
	 */
	public static function get_aggregate_function_from_alias($attribute_alias){
		$aggregator_pos = strpos($attribute_alias, self::AGGREGATION_SEPARATOR);
		if ($aggregator_pos !== false) {
			return substr($attribute_alias, $aggregator_pos+1);
		} else {
			return false;
		}
	}
	
	/**
	 * Returns a copy of this sorter still belonging to the same data sheet
	 * @return DataSorter
	 */
	public function copy(){
		return clone $this;
	}
	
}