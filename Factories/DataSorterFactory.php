<?php namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSorterInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\DataSheets\DataSorter;
use exface\Core\Exceptions\UnexpectedValueException;

abstract class DataSorterFactory extends AbstractFactory {
	
	public static function create_empty(Workbench $exface){
		return new DataSorter($exface);
	}
	
	/**
	 * 
	 * @param DataSheet $data_sheet
	 * @return DataSorterInterface
	 */
	public static function create_for_data_sheet(DataSheetInterface $data_sheet){
		$exface = $data_sheet->get_workbench();
		$instance = new DataSorter($exface);
		$instance->set_data_sheet($data_sheet);
		return $instance;
	}
	
	/**
	 * 
	 * @param DataSheet $data_sheet
	 * @param UxonObject $uxon
	 * @return DataSorterInterface
	 */
	public static function create_from_uxon(DataSheetInterface $data_sheet, UxonObject $uxon){
		$sorter = self::create_for_data_sheet($data_sheet);
		$sorter->import_uxon_object($uxon);
		return $sorter;
	}
	
	/**
	 * 
	 * @param DataSheet $data_sheet
	 * @param DataSorter | string | UxonObject $sorter_or_string_or_uxon
	 * @throws UnexpectedValueException
	 * @return DataSorterInterface
	 */
	public function create_from_anything(DataSheetInterface $data_sheet, $sorter_or_string_or_uxon){
		if ($sorter_or_string_or_uxon instanceof UxonObject){
			$result = static::create_from_uxon($this, $sorter_or_string_or_uxon);
		} elseif ($sorter_or_string_or_uxon instanceof DataSorter){
			$result = $sorter_or_string_or_uxon;
		} else {
			throw new UnexpectedValueException('Cannot set aggregator "' . $sorter_or_string_or_uxon . '": only instantiated data aggregators or uxon objects allowed!');
		}
		return $result;
	}
		
}
?>