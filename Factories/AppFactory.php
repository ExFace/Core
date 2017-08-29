<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\AppNotFoundError;
use exface\Core\CommonLogic\Workbench;

abstract class AppFactory extends AbstractNameResolverFactory
{

    /**
     * Creates a new app from the given name resolver
     *
     * @param NameResolver $name_resolver            
     * @return AppInterface
     */
    public static function create(NameResolverInterface $name_resolver)
    {
        $class = $name_resolver->getClassNameWithNamespace();
        if (! class_exists($class)) {
            $class = '\\exface\\Core\\CommonLogic\\Model\\App';
            //throw new AppNotFoundError('No class found for app "' . $name_resolver->getAliasWithNamespace() . '"!', '6T5DXWP');
        }
        $app = new $class($name_resolver);
        return $app;
    }

    /**
     *
     * @param string $alias_with_namespace            
     * @param Workbench $exface            
     * @return AppInterface
     */
    public static function createFromAlias($alias_with_namespace, Workbench $exface)
    {
        $name_resolver = NameResolver::createFromString($alias_with_namespace, NameResolver::OBJECT_TYPE_APP, $exface);
        return static::create($name_resolver);
    }
}
?>