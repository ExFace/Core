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
            // throw new AppNotFoundError('No class found for app "' . $name_resolver->getAliasWithNamespace() . '"!', '6T5DXWP');
        }
        $app = new $class($name_resolver);
        return $app;
    }

    /**
     * Creates a new app from the given NameResolver, UID or alias.
     * 
     * @param NameResolverInterface|string $anything
     * @param Workbench $exface
     * @return AppInterface
     */
    public static function createFromAnything($anything, Workbench $exface)
    {
        if ($anything instanceof NameResolverInterface) {
            return static::create($anything);
        } elseif (static::isUid($anything)) {
            return static::createFromUid($anything, $exface);
        } else {
            return static::createFromAlias($anything, $exface);
        }
    }

    /**
     * Creates a new app from the given alias.
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

    /**
     * Creates a new app from the given UID.
     * 
     * @param string $uid
     * @param Workbench $exface
     * @return AppInterface
     */
    public static function createFromUid($uid, Workbench $exface)
    {
        $appObject = $exface->model()->getObject('exface.Core.APP');
        $appDataSheet = DataSheetFactory::createFromObject($appObject);
        $appDataSheet->getColumns()->addFromAttribute($appObject->getAttribute('ALIAS'));
        $appDataSheet->getFilters()->addConditionsFromString($appObject, $appObject->getUidAttributeAlias(), $uid);
        $appDataSheet->dataRead();
        
        if ($appDataSheet->countRows() === 0) {
            throw new AppNotFoundError('No class found for app "' . $uid . '"!', '6T5DXWP');
        }
        return self::createFromAlias($appDataSheet->getRow(0)['ALIAS'], $exface);
    }

    /**
     * Returns if the passed value contains an app UID.
     * 
     * @param string $value
     * @return boolean
     */
    public static function isUid($value)
    {
        if (substr($value, 0, 2) == '0x') {
            return true;
        }
        return false;
    }
}
?>