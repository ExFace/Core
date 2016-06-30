<?php namespace exface\Core\Factories;

use exface\exface;
use exface\Core\EntityList;
use exface\Core\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Exceptions\FactoryError;

abstract class EntityListFactory extends AbstractUxonFactory {
	
	public static function crate(NameResolverInterface $name_resolver){
		throw new FactoryError('Creating empty entity lists from a name resolver currently unsupported!');
	}
	
	/**
	 * Creates an entity list for a given parent object. The object can be passed directly or specified by it's fully qualified alias (with namespace!)
	 * @param exface $exface
	 * @param Object|string $meta_object_or_alias
	 * @return EntityList
	 */
	public static function create_empty(exface &$exface, &$parent_object = null){
		$result = new EntityList($exface, $parent_object);
		return $result;
	}
	
	/**
	 * Creates an entity list for a given parent object, additionally specifying a name resolver for the entites.
	 * This enables the entity list to automatically import uxon objects correctly
	 * @param exface $exface
	 * @param Object|string $meta_object_or_alias
	 * @param NameResolverInterface $entity_name_resolver
	 * @return EntityList
	 */
	public static function create_with_entity_name_resolver(exface &$exface, &$parent_object = null, NameResolverInterface $entity_name_resolver = null){
		$result = static::create_empty($exface, $parent_object);
		if ($entity_name_resolver){
			$result->set_entity_name_resolver($entity_name_resolver);
		}
		return $result;
	}
	
	/**
	 * Creates an entity list for a given parent object, additionally specifying a factory class name for the entites.
	 * This enables the entity list to automatically import uxon objects correctly. It works even for those entities,
	 * that are not supported by the name resolver, but generally create_with_entity_name_resolver() is the better choice.
	 * @param exface $exface
	 * @param Object|string $meta_object_or_alias
	 * @param string $factory_class_name
	 * @return EntityList
	 */
	public static function create_with_entity_factory(exface &$exface, &$parent_object = null, $factory_class_name = null){
		$result = static::create_empty($exface, $parent_object);
		if ($factory_class_name){
			if (mb_strpos(NameResolver::CLASS_NAMESPACE_SEPARATOR, $factory_class_name)){
				$factory_class_name = NameResolver::get_default_factory_class_namespace() . $factory_class_name;
			}
			$result->set_entity_factory_name($factory_class_name);
		}
		return $result;
	}
	
}
?>