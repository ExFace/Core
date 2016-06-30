<?php
namespace exface\Core\Interfaces\Widgets;
interface iHaveFilters extends iHaveChildren {
	
	public function add_filter(\exface\Core\Widgets\AbstractWidget $filter_widget);
	public function get_filters();
	public function get_filters_applied();
	public function set_filters(array $filters);
	  
}