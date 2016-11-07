<?php
namespace exface\Core\Widgets;
class DataMatrix extends DataTable {
	private $label_column_id = null;
	private $data_column_id = null;
	
	protected function init(){
		parent:: init();
		$this->set_paginate(false);
		$this->set_show_row_numbers(false);
		$this->set_multi_select(false);
	}
	
	public function get_label_column_id() {
		return $this->label_column_id;
	}
	
	public function set_label_column_id($value) {
		$this->label_column_id = $value;
	}
	
	public function get_data_column_id() {
		return $this->data_column_id;
	}
	
	public function set_data_column_id($value) {
		$this->data_column_id = $value;
	}  
	
	/**
	 * Returns the data column widget or false if no data column specified
	 * @return \exface\Core\Widgets\DataColumn | boolean
	 */
	public function get_data_column(){
		if (!$result = $this->get_column($this->get_data_column_id())){
			$result = $this->get_column_by_attribute_alias($this->get_data_column_id());
		}
		return $result;
	}
	
	/**
	 * Returns the label column widget or false if no label column specified
	 * @return \exface\Core\Widgets\DataColumn | boolean
	 */
	public function get_label_column(){
		if (!$result = $this->get_column($this->get_label_column_id())){
			$result = $this->get_column_by_attribute_alias($this->get_label_column_id());
		}
		return $result;
	}
	  
}
?>