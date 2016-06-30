<?php namespace exface\Core\Factories;

use exface\exface;
use exface\Core\UxonObject;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataColumnTotalInterface;
use exface\Core\DataColumnTotal;

abstract class DataColumnTotalsFactory extends AbstractFactory {
	
	/**
	 * 
	 * @param DataColumnInterface $data_column
	 * @return DataColumnTotalInterface
	 */
	public static function create_empty(DataColumnInterface &$data_column){
		$result = new DataColumnTotal($data_column);
		return $result;
	}
	
	/**
	 * 
	 * @param DataColumnInterface $data_column
	 * @param string $function_name
	 * @return DataColumnTotalInterface
	 */
	public static function create_from_string(DataColumnInterface &$data_column, $function_name){
		$result = static::create_empty($data_column);
		$result->set_function($function_name);
		return $result;
	}
	
	/**
	 * 
	 * @param DataColumnInterface $data_column
	 * @param UxonObject $uxon
	 * @return DataColumnTotalInterface
	 */
	public static function create_from_uxon(DataColumnInterface &$data_column, UxonObject $uxon){
		$result = static::create_empty($data_column);
		$result->import_uxon_object($uxon);
		return $result;
	}		
}
?>