<?php
namespace exface\Core\QueryBuilder;
class QueryPartValue extends QueryPartAttribute {
	private $values = array();
	private $uids = array();
	
	public function is_valid(){
		if ($this->get_attribute()->get_data_address() != '') return true;
		return false;
	}
	
	public function set_value($value){
		$this->values[0] = $value;
	}
	
	public function set_values(array $values){
		$this->values = $values;
	}
	
	public function get_values(){
		return $this->values;
	}
	
	public function get_uids() {
		return $this->uids;
	}
	
	public function set_uids(array $uids_for_values) {
		$this->uids = $uids_for_values;
	}    
}
?>