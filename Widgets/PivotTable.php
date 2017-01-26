<?php
namespace exface\Core\Widgets;
class PivotTable extends DataTable {
	
	protected function init(){
		$this->set_paginate(false);
		$this->set_show_row_numbers(false);
		$this->set_multi_select(false);
	}
	
	  
}
?>