<?php namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

interface ActionInputOutputInterface extends iCanBeConvertedToUxon {
	/**
	 * @return DataSheetInterface
	 */
	public function get_data_sheet();
	
	/**
	 * 
	 * @param DataSheetInterface $data_sheet
	 * @return ActionInputOutputInterface
	 */
	public function set_data_sheet(DataSheetInterface $data_sheet);
	
	public function add_message($string);
	
	public function get_messages();
	
	public function print_messages();
	
	public function print_output();
}
?>