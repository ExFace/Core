<?php
namespace exface\Core\Interfaces\Widgets;
interface iHaveColumns extends iHaveChildren {
	
	public function add_column(\exface\Widgets\DataColumn $column);
	public function get_columns();
	public function set_columns(array $columns);
	  
}