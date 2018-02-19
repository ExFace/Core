<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\AppNotFoundError;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * Instantiates apps.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AppFactory extends AbstractSelectorFactory
{

    /**
     * Creates a new app from the given name resolver
     *
     * @param AppSelectorInterface $selector            
     * @return AppInterface
     */
    public static function create(SelectorInterface $selector)
    {
        if (! ($selector instanceof AppSelectorInterface)) {
            throw new InvalidArgumentException('Cannot create App from selector "' . get_class($selector) . '": expecting "AppSelector" or derivatives!');
        }
        
        if ($selector->isUid()) {
            return static::createFromUid($selector->toString(), $selector->getWorkbench());
        }
        
        $class = $selector->getClassname();
        if (! $selector->prototypeClassExists()) {
            $class = $selector->getClassnameOfDefaultPrototype();
        }
        $app = new $class($selector);
        return $app;
    }

    /**
     * Creates a new app from the given NameResolver, UID or alias.
     * 
     * @param AppSelectorInterface|string $anything
     * @param Workbench $workbench
     * @return AppInterface
     */
    public static function createFromAnything($anything, Workbench $workbench)
    {
        if ($anything instanceof AppSelectorInterface) {
            return static::create($anything);
        } else {
            return static::create(new AppSelector($workbench, $anything));
        }
    }

    /**
     * Creates a new app from the given alias.
     * 
     * @param string $alias_with_namespace            
     * @param Workbench $workbench            
     * @return AppInterface
     */
    public static function createFromAlias($alias_with_namespace, Workbench $workbench)
    {
        $selector = new AppSelector($workbench, $alias_with_namespace);
        return static::create($selector);
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
}
?>