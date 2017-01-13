<?php namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\WidgetDimension;

abstract class WidgetDimensionFactory extends AbstractFactory {
	
	/**
	 * Creates an empty dimension object
	 * @param exface $exface
	 * @return WidgetDimension
	 */
	public static function create_empty(Workbench $exface){
		return new WidgetDimension($exface);
	}
	
	/**
	 * Parses a dimension string (e.g. 100% or 68px) into a dimension object
	 * @param exface $exface
	 * @param string $string
	 * @return WidgetDimension
	 */
	public static function create_from_string(Workbench $exface, $string){
		$d = static::create_empty($exface);
		$d->parse_dimension($string);
		return $d;
	}
	
	/**
	 * 
	 * @param exface $exface
	 * @param string|WidgetDimension $string_or_dimension
	 * @return \exface\Core\CommonLogic\WidgetDimension
	 */
	public static function create_from_anything(Workbench $exface, $string_or_dimension){
		if ($string_or_dimension instanceof WidgetDimension){
			return $string_or_dimension;
		} else {
			return static::create_from_string($exface, $string_or_dimension);
		}
	}
	

}
?>