<?php namespace exface\Core\Factories;

use exface;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\AppNotFoundError;
use exface\Core\CommonLogic\Workbench;

abstract class AppFactory extends AbstractNameResolverFactory {
	
	/**
	 * Creates a new app from the given name resolver
	 * @param NameResolver $name_resolver
	 * @return AppInterface
	 */
	public static function create(NameResolverInterface $name_resolver){
		$exface = $name_resolver->get_workbench();
		$class = $name_resolver->get_class_name_with_namespace();
		if (!class_exists($class)){
			throw new AppNotFoundError('No class found for app "' . $name_resolver->get_alias_with_namespace() . '"!', '6T5DXWP');
		}
		$app = new $class($exface);
		$app->set_name_resolver($name_resolver);
		return $app;
	}
	
	/**
	 * 
	 * @param string $alias_with_namespace
	 * @param Workbench $exface
	 * @return AppInterface
	 */
	public static function create_from_alias($alias_with_namespace, Workbench $exface){
		$name_resolver = NameResolver::create_from_string($alias_with_namespace, NameResolver::OBJECT_TYPE_APP, $exface);
		return static::create($name_resolver);
	}
}
?>