<?php namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\WidgetLink;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;

abstract class WidgetLinkFactory extends AbstractUxonFactory {
	
	/**
	 * Creates an empty dimension object
	 * @param exface $exface
	 * @return WidgetLinkInterface
	 */
	public static function create_empty(Workbench $exface){
		return new WidgetLink($exface);
	}
	
	/**
	 * @param exface $exface
	 * @param string|\stdClass|UxonObject $string_or_object
	 * @param string $id_space
	 * @return WidgetLinkInterface
	 */
	public static function create_from_anything(Workbench $exface, $string_or_object, $id_space = null){
		if ($string_or_object instanceof WidgetLinkInterface){
			return $string_or_object;
		}
		$ref = static::create_empty($exface);
		if (!is_null($id_space)){
			$ref->set_widget_id_space($id_space);
		}
		$ref->parse_link($string_or_object);
		return $ref;
	}

}
?>