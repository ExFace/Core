<?php namespace exface\Core\Factories;

use exface\exface;
use exface\Core\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Model\Formula;

abstract class FormulaFactory extends AbstractNameResolverFactory {
	
	/**
	 * Creates a formula from the given name resolver and optionally specified array of arguments
	 * @param NameResolverInterface $name_resolver
	 * @param array $arguments
	 * @return Formula
	 */
	public static function create(NameResolverInterface $name_resolver, array $arguments = array()){
		$class = $name_resolver->get_class_name_with_namespace();
		$formula = new $class();
		$formula->init($arguments);
		return $formula;
	}
	
	/**
	 * Creates a Formula specified by the function name and an optional array of arguments.
	 * @param exface $exface
	 * @param string $function_name
	 * @param array $arguments
	 * @return Formula
	 */
	public static function create_from_string(exface &$exface, $function_name, array $arguments = array()){
		$name_resolver = $exface->create_name_resolver($function_name, NameResolver::OBJECT_TYPE_FORMULA);
		return static::create($name_resolver, $arguments);
	}
	
}
?>