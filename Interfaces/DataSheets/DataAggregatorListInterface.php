<?php namespace exface\Core\Interfaces\DataSheets;

use exface\exface;
use exface\Core\Interfaces\DataSheets\DataAggregatorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\EntityListInterface;

interface DataAggregatorListInterface extends EntityListInterface {
	
	
	/**
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\EntityListInterface::get_all()
	 * @return DataAggregatorInterface[]
	 */
	public function get_all();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\EntityListInterface::get()
	 * @return DataAggregator
	 */
	public function get($key);
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\EntityListInterface::get_first()
	 * @return DataAggregator
	 */
	public function get_first();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\EntityListInterface::get_last()
	 * @return DataAggregator
	 */
	public function get_last();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\EntityListInterface::get_nth()
	 * @return DataAggregator
	 */
	public function get_nth($number);
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\EntityListInterface::get_parent()
	 * @return DataSheetInterface
	 */
	public function get_parent();
	
	/**
	 * 
	 * @param string $attribute_alias
	 * @return DataAggregatorList
	 */
	public function add_from_string($attribute_alias);
	
	/**
	 * 
	 * @param array $uxon
	 * @return void
	 */
	public function import_uxon_array(array $uxon);

}
?>