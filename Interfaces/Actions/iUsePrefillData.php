<?php namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\UxonObject;

interface iUsePrefillData {
	
	/**
	 * @return DataSheetInterface
	 */
	public function get_prefill_data_sheet();
	
	/**
	 * 
	 * @param DataSheetInterface|UxonObject|string $any_data_sheet_source
	 * @return iUsePrefillData
	 */
	public function set_prefill_data_sheet($any_data_sheet_source);
	
	  
}