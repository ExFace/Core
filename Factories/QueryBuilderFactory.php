<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\Interfaces\Selectors\QueryBuilderSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\QueryBuilderInterface;
use exface\Core\CommonLogic\Selectors\QueryBuilderSelector;

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
        return static::createFromSelector($selector);
    }

    /**
     * Creates a new query (query builder instance) from the given identifier
     * - file path relative to the ExFace installation directory
     * - ExFace alias with namespace
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
}
?>