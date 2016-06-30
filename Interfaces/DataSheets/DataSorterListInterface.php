<?php namespace exface\Core\Interfaces\DataSheets;

use exface\exface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\EntityListInterface;

interface DataSorterListInterface extends EntityListInterface {
	
	
	/**
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\EntityListInterface::get_all()
	 * @return DataSorterInterface[]
	 */
	public function get_all();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\EntityListInterface::get()
	 * @return DataSorterInterface
	 */
	public function get($key);
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\EntityListInterface::get_first()
	 * @return DataSorterInterface
	 */
	public function get_first();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\EntityListInterface::get_last()
	 * @return DataSorterInterface
	 */
	public function get_last();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\EntityListInterface::get_nth()
	 * @return DataSorterInterface
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
	 * @return DataSorterListInterface
	 */
	public function add_from_string($attribute_alias, $direction = 'ASC');
	
	/**
	 * 
	 * @param array $uxon
	 * @return void
	 */
	public function import_uxon_array(array $uxon);

}
?>