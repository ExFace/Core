<?php namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Exceptions\InvalidArgumentException;

interface DataSorterInterface extends iCanBeConvertedToUxon, iCanBeCopied {
	
	function __construct(DataSheetInterface $data_sheet);
	
	/**
	 * @return string
	 */
	public function get_attribute_alias() ;
	
	/**
	 * 
	 * @param unknown $value
	 * @return DataSorterInterface
	 */
	public function set_attribute_alias($value) ;
	
	/**
	 * @return string
	 */
	public function get_direction() ;
	
	/**
	 * 
	 * @param string $value
	 * @throws InvalidArgumentException
	 * @return DataSorterInterface
	 */
	public function set_direction($value) ;
	
	/**
	 * @return DataSheetInterface
	 */
	public function get_data_sheet() ;
	
	/**
	 * 
	 * @param DataSheetInterface $data_sheet
	 * @return DataSorterInterface
	 */
	public function set_data_sheet(DataSheetInterface $data_sheet) ;
	
}