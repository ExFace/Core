<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\EntityList;
use exface\Core\Interfaces\Model\MetaObjectInterface;

abstract class EntityListFactory extends AbstractUxonFactory
{
    /**
     * Creates an entity list for a given parent object.
     * The object can be passed directly or specified by it's fully qualified alias (with namespace!)
     *
     * @param Workbench $exface            
     * @param MetaObjectInterface|string $meta_object_or_alias            
     * @return EntityList
     */
    public static function createEmpty(Workbench $exface, $parent_object = null)
    {
        $result = new EntityList($exface, $parent_object);
        return $result;
    }

    /**
     * Creates an entity list for a given parent object, additionally specifying a factory class name for the entites.
     * This enables the entity list to automatically import uxon objects correctly. It works even for those entities,
     * that are not supported by the name resolver, but generally create_with_entity_name_resolver() is the better choice.
     *
     * @param Workbench $exface            
     * @param MetaObjectInterface|string $meta_object_or_alias            
     * @param string $factory_class_name            
     * @return EntityList
     */
    public static function createWithEntityFactory(Workbench $exface, $parent_object = null, $factory_class_name)
    {
        $result = static::createEmpty($exface, $parent_object);
        if ($factory_class_name) {
            $result->setEntityFactoryName($factory_class_name);
        }
        return $result;
    }
}
?>