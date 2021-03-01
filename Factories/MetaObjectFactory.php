<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;

/**
 * Instantiates meta objects
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class MetaObjectFactory extends AbstractStaticFactory
{
    /**
     *
     * @param MetaObjectSelectorInterface $selector
     * @return MetaObjectInterface
     */
    public static function create(MetaObjectSelectorInterface $selector) : MetaObjectInterface
    {
        return $selector->getWorkbench()->model()->getObject($selector);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $uidOrAlias
     * @return MetaObjectInterface
     */
    public static function createFromString(WorkbenchInterface $workbench, string $uidOrAlias) : MetaObjectInterface
    {
        return $workbench->model()->getObject($uidOrAlias);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $alias
     * @param string $namespace
     * @return MetaObjectInterface
     */
    public static function createFromAliasAndNamespace(WorkbenchInterface $workbench, string $alias, string $namespace) : MetaObjectInterface
    {
        return $workbench->model()->getObjectByAlias($alias, $namespace);
    }
    
    /**
     * 
     * @param AppInterface $app
     * @param string $alias
     * @return MetaObjectInterface
     */
    public static function createFromApp(AppInterface $app, string $alias) : MetaObjectInterface
    {
        return $app->getWorkbench()->model()->getObjectByAlias($alias, $app->getAliasWithNamespace());
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $uid
     * @return MetaObjectInterface
     */
    public static function createFromUid(WorkbenchInterface $workbench, string $uid) : MetaObjectInterface
    {
        return $workbench->model()->getObjectById($uid);
    }
}