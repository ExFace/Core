<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Interfaces\WidgetInterface;

interface iHaveValue extends WidgetInterface {
	
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
	
	/**
	 * Returns the placeholder text to be used by templates if the widget has no value.
	 *
	 * @return string
	 */
	public function get_empty_text();
	
	/**
	 * Defines the placeholder text to be used if the widget has no value. Set to blank string to remove the placeholder.
	 *
	 * @param string $value
	 * @return iHaveValue
	 */
	public function set_empty_text($value);
	
}