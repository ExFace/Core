<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\Interfaces\Selectors\QueryBuilderSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\QueryBuilderInterface;
use exface\Core\CommonLogic\Selectors\QueryBuilderSelector;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\QueryBuilders\ModelLoaderQueryBuilder;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class QueryBuilderFactory extends AbstractSelectableComponentFactory
{

    /**
     * Creates a new query builder based on the given name resolver
     *
     * @param QueryBuilderSelectorInterface $selector            
     * @return AbstractQueryBuilder
     */
    public static function create(QueryBuilderSelectorInterface $selector) : QueryBuilderInterface
    {
        if (self::isModelLoaderQueryBuilder($selector)) {
            return self::createModelLoaderQueryBuilder($selector->getWorkbench());
        }
        return static::createFromSelector($selector);
    }

    /**
     * Creates a new query (query builder instance) from the given identifier
     * - file path relative to the ExFace installation directory
     * - class name
     *
     * @param WorkbenchInterface $workbench            
     * @param string $selectorString            
     * @return AbstractQueryBuilder
     */
    public static function createFromString(WorkbenchInterface $workbench, string $selectorString) : QueryBuilderInterface
    {
        $selector = new QueryBuilderSelector($workbench, $selectorString);
        return static::create($selector);
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return QueryBuilderInterface
     */
    public static function createForObject(MetaObjectInterface $object) : QueryBuilderInterface
    {
        $qb = static::createFromString($object->getWorkbench(), $object->getQueryBuilder());
        $qb->setMainObject($object);
        return $qb;
    }
    
    /**
     * Instantiates the query builder used for the current metamodel storage.
     * 
     * @param WorkbenchInterface $workbench
     * @return QueryBuilderInterface
     */
    public static function createModelLoaderQueryBuilder(WorkbenchInterface $workbench) : QueryBuilderInterface
    {
        return self::createFromString($workbench, $workbench->getConfig()->getOption('METAMODEL.QUERY_BUILDER'));
    }
    
    /**
     *
     * @param QueryBuilderSelectorInterface $selector
     * @return bool
     */
    protected static function isModelLoaderQueryBuilder(QueryBuilderSelectorInterface $selector) : bool
    {
        switch (true) {
            case $selector->isClassname() && strcasecmp($selector->toString(), '\\' . ModelLoaderQueryBuilder::class) === 0:
            case $selector->isFilepath() && strcasecmp(Filemanager::pathNormalize($selector->toString()), Filemanager::pathNormalize(ModelLoaderQueryBuilder::class) . '.php') === 0:
                return true;
        }
        return false;
    }
}