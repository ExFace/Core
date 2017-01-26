<?php namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\EntityListInterface;

interface DataColumnTotalsListInterface extends EntityListInterface {
	/**
	 * Returns all elements as an array
	 * @return DataColumnTotalInterface[]
	 */
	public function get_all();
	
	/**
	 * Returns the first element of the list
	 * @return DataColumnTotalInterface
	 */
	public function get_first();
	
	/**
	 * Returns the last element of the list
	 * @return DataColumnTotalInterface
	 */
	public function get_last();
	
	/**
	 * Adds an entity to the list under the given key. If no key is given, the entity is appended to the end of the list.
	 * CAUTION: it is not advisable to mix entries with keys and without them in one list!
	 *
	 * @param DataColumnTotalInterface $entity
	 * @param mixed $key
	 * @return DataColumnTotalsListInterface
	 */
	public function add($entity, $key = null);
	
	/**
	 * Removes the given entity from the list
	 * @param mixed $entity
	 * @return DataColumnTotalsListInterface
	 */
	public function remove($entity);
		
	/**
	 * Returns the entity, that was stored under the given key. Returns NULL if the key is not present in the list.
	 * @param mixed $key
	 * @return DataColumnTotalsListInterface
	 */
	public function get($key);
	
	/**
	 * Returns the n-th entity in the list (starting from 1 for the first entity). Returns NULL if the list is smaller than $number.
	 * @param integer $number
	 * @return DataColumnTotalInterface
	 */
	public function get_nth($number);
	
	/**
	 * Returns the lists parent object
	 * @return DataSheetInterface
	 */
	public function get_parent();
	
	/**
	 * Sets the lists parent object
	 * @param DataSheetInterface $parent_object
	 */
	public function set_parent($parent_object);
	
}
?>