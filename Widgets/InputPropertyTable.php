<?php
namespace exface\Widgets;
class InputPropertyTable extends Input {
	 private $allow_add_properties = true;
	 private $allow_remove_properties = true;
	 
	 public function get_allow_add_properties() {
	 	return $this->allow_add_properties;
	 }
	 
	 public function set_allow_add_properties($value) {
	 	$this->allow_add_properties = $value;
	 }
	 
	 public function get_allow_remove_properties() {
	 	return $this->allow_remove_properties;
	 }
	 
	 public function set_allow_remove_properties($value) {
	 	$this->allow_remove_properties = $value;
	 }    
}
?>