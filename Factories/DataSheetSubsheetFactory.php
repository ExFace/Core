<?php namespace exface\Core\Factories;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\DataSheets\DataSheetSubsheet;

abstract class DataSheetSubsheetFactory {
	
	/**
	 * Returns a new subsheet based on the specified object for the give data parent data sheet
	 * @param Object $meta_object
	 * @param DataSheet $parent_sheet
	 * @return \exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface
	 */
	public static function create_for_object(Object $meta_object, DataSheetInterface $parent_sheet){
		$result = new DataSheetSubsheet($meta_object);
		$result->set_parent_sheet($parent_sheet);
		return $result;
	}
	
	/**
	 * 
	 * @param DataSheet $data_sheet
	 * @param DataSheet $parent_sheet
	 * @return \exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface
	 */
	public static function create_from_data_sheet(DataSheetInterface $data_sheet, DataSheetInterface $parent_sheet){
		$meta_object = $data_sheet->get_meta_object();
		$result = self::create_for_object($meta_object, $parent_sheet);
		$result->import_uxon_object($data_sheet->export_uxon_object());
		return $result;
	}
	
}
?>