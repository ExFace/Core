<?php namespace exface\Core\CommonLogic\DataSheets;

class DataSheetSubsheet extends DataSheet{
	
	private $parent_sheet = null;
	private $join_parent_on_column_id = null;
    
    public function get_parent_sheet() {
    	return $this->parent_sheet;
    }
    
    public function set_parent_sheet($value) {
    	$this->parent_sheet = $value;
    	return $this;
    }
    
    public function get_join_parent_on_column_id() {
    	return $this->join_parent_on_column_id;
    }
    
    public function set_join_parent_on_column_id($value) {
    	$this->join_parent_on_column_id = $value;
    	return $this;
    }      
}

?>