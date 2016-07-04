<?php
namespace exface\Core\CommonLogic\QueryBuilder;
class QueryPartTotal extends QueryPartAttribute {
	private $row = 0;
	private $function = null;
	
	public function get_row() {
		return $this->row;
	}
	
	public function set_row($value) {
		$this->row = $value;
	}
	
	public function get_function() {
		return $this->function;
	}
	
	public function set_function($value) {
		$this->function = $value;
	}    
}
?>