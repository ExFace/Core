<?php namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\exface;
use exface\Core\UxonObject;
use exface\Core\Interfaces\DataSheets\DataAggregatorInterface;
use exface\Core\Exceptions\FactoryError;
use exface\Core\DataAggregator;

abstract class DataAggregatorFactory extends AbstractFactory {
	
	/**
	 * 
	 * @param DataSheetInterface $data_sheet
	 * @return DataAggregatorInterface
	 */
	public static function create_for_data_sheet(DataSheetInterface &$data_sheet){
		return new DataAggregator($data_sheet);
	}
	
	/**
	 * 
	 * @param DataSheetInterface $data_sheet
	 * @param UxonObject $uxon
	 * @return DataAggregatorInterface
	 */
	public static function create_from_uxon(DataSheetInterface &$data_sheet, UxonObject $uxon){
		$result = self::create_for_data_sheet($data_sheet);
		$result->import_uxon_object($uxon);
		return $result;
	}
	
	/**
	 * 
	 * @param DataSheetInterface $data_sheet
	 * @param unknown $aggregator_or_string_or_uxon
	 * @throws FactoryError
	 * @return DataAggregatorInterface
	 */
	public function create_from_anything(DataSheetInterface &$data_sheet, $aggregator_or_string_or_uxon){
		if ($aggregator_or_string_or_uxon instanceof UxonObject){
			$result = static::create_from_uxon($this, $aggregator_or_string_or_uxon);
		} elseif ($aggregator_or_string_or_uxon instanceof DataAggregator){
			$result = $aggregator_or_string_or_uxon;
		} else {
			throw new FactoryError('Cannot set aggregator "' . $aggregator_or_string_or_uxon . '": only instantiated data aggregators or uxon objects allowed!');
		}
		return $result;
	}
		
}
?>