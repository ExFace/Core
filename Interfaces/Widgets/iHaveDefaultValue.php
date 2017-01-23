<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\Model\Expression;

interface iHaveDefaultValue extends iTakeInput {
	
	/**
	 * @return string
	 */
	public function get_default_value();
	
	/**
	 * @return Expression
	 */
	public function get_default_value_expression();
	
	/**
	 * @return boolean
	 */
	public function get_ignore_default_value();
	
	/**
	 * 
	 * @param boolean $true_or_false
	 * @return iHaveDefaultValue
	 */
	public function set_ignore_default_value($true_or_false);
	
}