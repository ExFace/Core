<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\Widgets\DataColumnGroup;

interface iHaveColumnGroups extends iHaveChildren {
	
	public function add_column_group(DataColumnGroup $column);
	public function get_column_groups();
	  
}