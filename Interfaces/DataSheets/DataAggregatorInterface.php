<?php namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\CommonLogic\UxonObject;

interface DataAggregatorInterface extends iCanBeConvertedToUxon, iCanBeCopied {
	
	function __construct(DataSheetInterface &$data_sheet);
	
	public function get_attribute_alias();
	
	public function set_attribute_alias($value);
	
	public function get_data_sheet();
	
	public function set_data_sheet(DataSheetInterface &$data_sheet);
	
	public function export_uxon_object();
	
	public function import_uxon_object(UxonObject $uxon);
	
	/**
	 * PRODUCT->SIZE:CONCAT(',') --> CONCAT(',')
	 * @param string $attribute_alias
	 * @return string|boolean
	 */
	public static function get_aggregate_function_from_alias($attribute_alias);
	
}