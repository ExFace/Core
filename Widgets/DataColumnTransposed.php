<?php namespace exface\Core\Widgets;


/**
 * 
 * 
 * @author Andrej Kabachnik 
 * 
 */
class DataColumnTransposed extends DataColumn {
	
	private $label_attribute_alias = null;
	private $label_sort_direction = null;
	
	public function get_label_attribute_alias() {
		return $this->label_attribute_alias;
	}
	
	public function set_label_attribute_alias($value) {
		$this->label_attribute_alias = $value;
		return $this;
	}
	
	public function get_label_sort_direction() {
		return $this->label_sort_direction;
	}
	
	public function set_label_sort_direction($value) {
		$this->label_sort_direction = $value;
		return $this;
	}
	
	  
}
?>