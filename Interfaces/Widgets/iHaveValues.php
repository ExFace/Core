<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\Model\Expression;

interface iHaveValues extends iHaveValue {
	
	/**
	 * @return array
	 */
	public function get_values();
	
	/**
	 * 
	 * @param Expression|string $expression_or_delimited_list
	 */
	public function set_values($expression_or_delimited_list);
	
	/**
	 * 
	 * @param array $values
	 */
	public function set_values_from_array(array $values);
	
}