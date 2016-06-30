<?php namespace exface\Core\Factories;

use exface\exface;
use exface\Core\NameResolver;
use exface\Core\UxonObject;
use exface\Core\Model\Object;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\NameResolverInterface;

abstract class BehaviorFactory extends AbstractNameResolverFactory {
	
	/**
	 * 
	 * @param NameResolverInterface $name_resolver
	 * @return BehaviorInterface
	 */
	public static function create(NameResolverInterface $name_resolver, Object &$object = null){
		$class = $name_resolver->get_class_name_with_namespace();
		$instance = new $class($object);
		return $instance;
	}
	
	/**
	 * 
	 * @param Object $object
	 * @param string $behavior_name
	 * @param UxonObject $uxon
	 * @return BehaviorInterface
	 */
	public static function create_from_uxon(Object &$object, $behavior_name, UxonObject $uxon){
		$exface = $object->exface();
		$name_resolver = NameResolver::create_from_string($behavior_name, NameResolver::OBJECT_TYPE_BEHAVIOR, $exface);
		$class = $name_resolver->get_class_name_with_namespace();
		$instance = new $class($object);
		$instance->import_uxon_object($uxon);
		$instance->set_name_resolver($name_resolver);
		return $instance;
	}

}
?>