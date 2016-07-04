<?php namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Interfaces\WidgetInterface;

interface iShowSingleAttribute extends WidgetInterface {
	/**
	 * @return Attribute
	 */
	public function get_attribute();
	
	/**
	 * @return string
	 */
	public function get_attribute_alias();
}