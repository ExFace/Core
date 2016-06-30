<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\Model\Expression;

interface iHaveValue {
	
	/**
	 * @return string
	 */
	public function get_value();
	
	/**
	 * 
	 * @param Expression|string $expression_or_string
	 */
	public function set_value($value);
	
	/**
	 *
	 * @return Expression
	 */
	public function get_value_expression();
}