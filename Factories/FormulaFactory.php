<?php namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\CommonLogic\Model\Formula;

abstract class FormulaFactory extends AbstractNameResolverFactory {
	
	/**
	 * Creates a formula from the given name resolver and optionally specified array of arguments
	 * @param NameResolverInterface $name_resolver
	 * @param array $arguments
	 * @return Formula
	 */
	public static function create(NameResolverInterface $name_resolver, array $arguments = array()){
		$class = $name_resolver->get_class_name_with_namespace();
		$workbench = $name_resolver->get_workbench();
		$formula = new $class($workbench);
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
	public static function create_from_string(Workbench $exface, $function_name, array $arguments = array()){
		$name_resolver = $exface->create_name_resolver($function_name, NameResolver::OBJECT_TYPE_FORMULA);
		return static::create($name_resolver, $arguments);
	}
	
}
?>