<?php
namespace exface\Core\CommonLogic\QueryBuilder;
class QueryPartSorter extends QueryPartAttribute {
	private $order;
	private $apply_after_reading = false;
	
	public function get_order() {
		return $this->order;
	}
	
	public function set_order($value) {
		if (!$value) $value = 'ASC';
		$this->order = $value;
	}	  
	
	/**
	 *
	 * @return boolean
	 */
	public function get_apply_after_reading() {
		return $this->apply_after_reading;
	}
	
	/**
	 *
	 * @param boolean $value
	 * @return QueryPartSorter
	 */
	public function set_apply_after_reading($value) {
		$this->apply_after_reading = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}
}

?>