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
	 * Returns the link to the widget, this widget's value is linked to. Returns NULL if the value of this widget is not a link.
	 * 
	 * The widget link will be resolved relative to the id space of this widget.
	 * 
	 * @return NULL|\exface\Core\Interfaces\Widgets\WidgetLinkInterface
	 */
	public function get_value_widget_link();
	
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