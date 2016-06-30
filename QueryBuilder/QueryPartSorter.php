<?php
namespace exface\Core\QueryBuilder;
class QueryPartSorter extends QueryPartAttribute {
	private $order;
	
	public function get_order() {
		return $this->order;
	}
	
	public function set_order($value) {
		if (!$value) $value = 'ASC';
		$this->order = $value;
	}	  
}

?>