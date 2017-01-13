<?php namespace exface\Core\Interfaces;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Workbench;

/**
 * The EntityList is a generic container for all kinds of object collections or lists in ExFace. Basically, it is an array with a proper API and some
 * enhancements. It can still be used in foreach() statements just like a normal array. It may be used directly or via derived
 * classes. 
 * 
 * Using the EntityList instead of arrays is strongly recommended! It ensures, that a common API is used for all sorts of collections like
 * attributes or relations in a meta object, columns in a data sheet, etc. Using the EntityList simplifies the entity classes by taking 
 * over all sorts of add_xxx and get_by_xxx methods. The EntityList can be quickly extended by entity-specific getters and setters like
 * get_by_data_type() for data sheet columns, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
interface EntityListInterface extends iCanBeConvertedToUxon, \IteratorAggregate {
	
	/**
	 * Returns all entities in the list as an array. It is an assosiative array, if key were explicitly speicified when filling the list.
	 * @return array
	 */
	public function get_all();
	
	/**
	 * Adds an entity to the list under the given key. If no key is given, the entity is appended to the end of the list.
	 * CAUTION: it is not advisable to mix entries with keys and without them in one list!
	 * 
	 * @param mixed $entity
	 * @param mixed $key
	 * @return UxonObjectList
	 */
	public function add($entity, $key = null);
	
	/**
	 * Removes the given entity from the list
	 * @param mixed $entity
	 * @return UxonObjectList
	 */
	public function remove($entity);
	/**
	 * Removes the entity matching the given key
	 * @param mixed $key
	 */
	public function remove_by_key($key);
	
	/**
	 * Returns the entity, that was stored under the given key. Returns NULL if the key is not present in the list.
	 * @param mixed $key
	 * @return mixed
	 */
	public function get($key);
	
	/**
	 * Returns the first entity in the list or NULL if the list is empty.
	 * @return mixed
	 */
	public function get_first();
	
	/**
	 * Returns the last entity in the list or NULL if the list is empty.
	 * @return mixed
	 */
	public function get_last();
	
	/**
	 * Returns the n-th entity in the list (starting from 1 for the first entity). Returns NULL if the list is smaller than $number.
	 * @param integer $number
	 */
	public function get_nth($number);
	
	/**
	 * Returns the lists parent object
	 */
	public function get_parent();
	
	/**
	 * Sets the lists parent object 
	 * @param mixed $parent_object
	 */
	public function set_parent($parent_object);
	
	/**
	 * Returns the current number of entities in the list.
	 * @return integer
	 */
	public function count();
	
	/**
	 * Returns TRUE, if the list is empty and FALSE otherwise
	 * @return boolean
	 */
	public function is_empty();
	
	 
	/**
	 * Sorts the list by key values
	 * @return EntityList
	 */
	public function sort_by_key();
	
	/**
	 * Uses the given array of UXON objects to populate the entity list. Each UXON object in the array
	 * will be instantiated and added to the list.
	 * @param array $uxon
	 * @return void
	 */
	public function import_uxon_array(array $uxon);
	
	/**
	 * Returns a new entity list, containing all entities of this one and all entities of the given other list, that
	 * were not present in the current one.
	 * @param EntityListInterface $other_list
	 * @return EntityListInterface
	 */
	public function merge(EntityListInterface $other_list);
}
?>