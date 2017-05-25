<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\NameResolver;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Exceptions\Behaviors\BehaviorNotFoundError;

abstract class BehaviorFactory extends AbstractNameResolverFactory
{

    /**
     *
     * @param NameResolverInterface $name_resolver            
     * @return BehaviorInterface
     */
    public static function create(NameResolverInterface $name_resolver, Object $object = null)
    {
        $class = $name_resolver->getClassNameWithNamespace();
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
    public static function createFromUxon(Object $object, $behavior_name, UxonObject $uxon)
    {
        $exface = $object->getWorkbench();
        $name_resolver = NameResolver::createFromString($behavior_name, NameResolver::OBJECT_TYPE_BEHAVIOR, $exface);
        if (! $name_resolver->classExists()) {
            throw new BehaviorNotFoundError($object, 'Behavior "' . $behavior_name . '" of object "' . $object->getAliasWithNamespace() . '" not found!');
        }
        $class = $name_resolver->getClassNameWithNamespace();
        $instance = new $class($object);
        $instance->importUxonObject($uxon);
        $instance->setNameResolver($name_resolver);
        return $instance;
    }
}
?>