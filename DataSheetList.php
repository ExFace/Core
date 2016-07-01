<?php namespace exface\Core;

use exface\Core\Model\Object;
use exface\Core\exface;
use exface\Core\Exceptions\DataSheetException;

class DataSheetList extends EntityList {
	
	/**
	 * Adds a data sheet
	 * @param DataSheet $column
	 * @param mixed $key
	 * @return DataSheetList
	 */
	public function add(&$sheet, $key = null){
		if ($sheet instanceof DataSheetSubsheet) {
			$result = parent::add($sheet, $key);
		} else {
			$result = $this;
			throw new DataSheetException('Adding regular data sheets as subsheets not implemented yet!');
		}
		return $result;
	}
	
	/**
	 * @return DataSheetInterface[]
	 */
	public function get_all(){
		return parent::get_all();
	}
	
	/**
	 * Returns all subsheets, that have the specified meta object as their base object
	 * @param Object $object
	 * @return DataColumn[]
	 */
	public function get_by_object(Object $object){
		$result = array();
		foreach ($this->get_all() as $sheet){
			if ($sheet->get_meta_object()->get_id() == $object->get_id()){
				$result[] = $sheet;
			}
		}
		return $result;
	}
	
	/**
	 * Returns the data sheet, that was stored under the given key
	 * @param mixed $key
	 * @return DataSheetInterface
	 */
	public function get($key){
		return parent::get($key);
	}
	
	/**
	 * Returns the parent data sheet
	 * @return DataSheetInterface 
	 */
	public function get_parent() {
		return parent::get_parent();
	}
}
?>