<?php namespace exface\Core\Factories;

use exface;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\Interfaces\NameResolverInterface;

abstract class DataConnectorFactory extends AbstractNameResolverFactory {
	
	/**
	 * Creates a data connector from the given name resolver and an optional config array
	 * @param NameResolverInterface $name_resolver
	 * @param array $config
	 * @return AbstractDataConnector
	 */
	public static function create(NameResolverInterface $name_resolver, array $config = null){
		$class = $name_resolver->get_class_name_with_namespace();
		$exface = $name_resolver->get_workbench();
		$instance = new $class($exface, $config);
		$instance->set_name_resolver($name_resolver);
		return $instance;
	}
	
	/**
	 * Creates a data connector from the given identifier 
	 * - file path relative to the ExFace installation directory
	 * - ExFace alias with namespace
	 * - class name
	 * @param exface\Core\CommonLogic\Workbench $exface
	 * @param string $path_or_qualified_alias
	 * @param array $config
	 * @return AbstractDataConnector
	 */
	public static function create_from_alias(exface\Core\CommonLogic\Workbench $exface, $path_or_qualified_alias, array $config = null){
		$name_resolver = $exface->create_name_resolver($path_or_qualified_alias, NameResolver::OBJECT_TYPE_DATA_CONNECTOR);
		return static::create($name_resolver, $config);
	}
}
?>