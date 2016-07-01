<?php namespace exface\Core\Factories;

use exface\Core\exface;
use exface\Core\Model\Expression;
use exface\Core\Model\Attribute;

abstract class ExpressionFactory {
	
	/**
	 * Parses a string expression into an ExFace expression object. It is highly recommended to pass the meta object, the expression is related to as well. Otherwise
	 * attribute_aliases cannot be parsed properly.
	 * TODO Make the object a mandatory parameter. This requires a lot of changes to formulas, however. Probably will do that when rewriting the formula parser.
	 * @param exface $exface
	 * @param string $expression
	 * @param object $object
	 * @return Expression
	 */
	public static function create_from_string(exface &$exface, $string, $meta_object = null){
		return new Expression($exface, $string, $meta_object);
	}
	
	/**
	 * 
	 * @param Attribute $attribute
	 * @return Expression
	 */
	public static function create_from_attribute(Attribute $attribute) {
		$exface = $attribute->get_object()->exface();
		return self::create_from_string($exface, $attribute->get_alias_with_relation_path(), $attribute->get_relation_path()->get_start_object());
	}
	
}
?>