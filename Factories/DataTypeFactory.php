<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Exceptions\DataTypeNotFoundError;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\Model\DataTypeInterface;

abstract class DataTypeFactory extends AbstractNameResolverFactory
{

    /**
     *
     * @param NameResolverInterface $name_resolver            
     * @return DataTypeInterface
     */
    public static function create(NameResolverInterface $name_resolver)
    {
        if ($name_resolver->classExists()){
            $class = $name_resolver->getClassNameWithNamespace();
            return new $class($name_resolver);
        } else {
            throw new DataTypeNotFoundError('Data type "' . $name_resolver->getAliasWithNamespace() . '" not found in class "' . $name_resolver->getClassNameWithNamespace() . '"!');
        }
    }

    /**
     * 
     * @param Workbench $exface            
     * @param string $alias_with_namespace            
     * @return DataTypeInterface
     */
    public static function createFromAlias(Workbench $workbench, $alias_with_namespace)
    {
        $name_resolver = NameResolver::createFromString($alias_with_namespace, NameResolver::OBJECT_TYPE_DATATYPE, $workbench);
        return static::create($name_resolver);
    }
    
    /**
     * 
     * @param Workbench $workbench
     * @return DataTypeInterface
     */
    public static function createBaseDataType(Workbench $workbench)
    {
        $name_resolver = NameResolver::createFromString('String', NameResolver::OBJECT_TYPE_DATATYPE, $workbench);
        return static::create($name_resolver);
    }
    
    public static function createFromPrototype(Workbench $workbench, $resolvable_name)
    {
        return static::createFromAlias($workbench, $resolvable_name);
    }
}
?>