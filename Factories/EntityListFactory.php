<?php

namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\EntityList;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;

abstract class EntityListFactory extends AbstractUxonFactory
{

    public static function crate(NameResolverInterface $name_resolver)
    {
        throw new \LogicException('Creating empty entity lists from a name resolver currently unsupported!');
    }

    /**
     * Creates an entity list for a given parent object.
     * The object can be passed directly or specified by it's fully qualified alias (with namespace!)
     * 
     * @param exface $exface            
     * @param Object|string $meta_object_or_alias            
     * @return EntityList
     */
    public static function createEmpty(Workbench $exface, $parent_object = null)
    {
        $result = new EntityList($exface, $parent_object);
        return $result;
    }

    /**
     * Creates an entity list for a given parent object, additionally specifying a name resolver for the entites.
     * This enables the entity list to automatically import uxon objects correctly
     * 
     * @param exface $exface            
     * @param Object|string $meta_object_or_alias            
     * @param NameResolverInterface $entity_name_resolver            
     * @return EntityList
     */
    public static function createWithEntityNameResolver(Workbench $exface, $parent_object = null, NameResolverInterface $entity_name_resolver = null)
    {
        $result = static::createEmpty($exface, $parent_object);
        if ($entity_name_resolver) {
            $result->setEntityNameResolver($entity_name_resolver);
        }
        return $result;
    }

    /**
     * Creates an entity list for a given parent object, additionally specifying a factory class name for the entites.
     * This enables the entity list to automatically import uxon objects correctly. It works even for those entities,
     * that are not supported by the name resolver, but generally create_with_entity_name_resolver() is the better choice.
     * 
     * @param exface $exface            
     * @param Object|string $meta_object_or_alias            
     * @param string $factory_class_name            
     * @return EntityList
     */
    public static function createWithEntityFactory(Workbench $exface, $parent_object = null, $factory_class_name = null)
    {
        $result = static::createEmpty($exface, $parent_object);
        if ($factory_class_name) {
            if (mb_strpos(NameResolver::CLASS_NAMESPACE_SEPARATOR, $factory_class_name)) {
                $factory_class_name = NameResolver::getDefaultFactoryClassNamespace() . $factory_class_name;
            }
            $result->setEntityFactoryName($factory_class_name);
        }
        return $result;
    }
}
?>