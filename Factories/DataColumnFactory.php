<?php namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\exface;
use exface\Core\UxonObject;
use exface\Core\DataColumn;
use exface\Core\Model\Expression;

abstract class DataColumnFactory extends AbstractFactory {
	
	/**
	 * 
	 * @param DataSheet $data_sheet
	 * @param unknown $expression_or_string
	 * @param string $name
	 * @return DataColumn
	 */
	public static function create_from_string(DataSheetInterface &$data_sheet, $expression_or_string, $name = null){
		return new DataColumn($expression_or_string, $name, $data_sheet);
	}
	
	/**
	 * 
	 * @param DataSheet $data_sheet
	 * @param Expression $expression
	 * @param string $name
	 * @return DataColumn
	 */
	public static function create_from_expression(DataSheetInterface &$data_sheet, Expression $expression, $name = null){
		return new DataColumn($expression, $name, $data_sheet);
	}
	
	/**
	 * 
	 * @param DataSheet $data_sheet
	 * @param UxonObject $uxon
	 * @return DataColumn
	 */
	public static function create_from_uxon(DataSheetInterface &$data_sheet, UxonObject $uxon){
		$result = self::create_from_string($data_sheet, ($uxon->expression ? $uxon->expression : $uxon->attribute_alias), $uxon->name);
		$result->import_uxon_object($uxon);
		return $result;
	}		
}
?>