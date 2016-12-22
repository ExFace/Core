<?php namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;

interface DataColumnTotalInterface extends iCanBeConvertedToUxon {
		
	function __construct(DataColumnInterface &$column, $function_name = null);
	
	/**
	 * @return DataColumnInterface
	 */
	public function get_column();
	
	/**
	 * 
	 * @param DataColumnInterface $value
	 */
	public function set_column(DataColumnInterface &$value); 
	
	/**
	 * @return string
	 */
	public function get_function();
	
	/**
	 * 
	 * @param string $value
	 * @throws InvalidArgumentException
	 * @return \exface\Core\Interfaces\DataSheets\DataColumnTotalInterface
	 */
	public function set_function($value);
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::export_uxon_object()
	 */
	public function export_uxon_object();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::import_uxon_object()
	 */
	public function import_uxon_object (UxonObject $uxon);
	
}