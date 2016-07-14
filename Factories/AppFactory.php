<?php namespace exface\Core\Factories;

use exface;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\AppInterface;

abstract class AppFactory extends AbstractNameResolverFactory {
	
	/**
	 * Creates a new app from the given name resolver
	 * @param NameResolver $name_resolver
	 * @return AppInterface
	 */
	public static function create(NameResolverInterface $name_resolver){
		$exface = $name_resolver->get_workbench();
		$class = $name_resolver->get_class_name_with_namespace();
		$app = new $class($exface);
		$app->set_name_resolver($name_resolver);
		return $app;
	}
}
?>