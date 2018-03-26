<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\QueryBuilderSelectorInterface;

/**
 * Generic implementation of the QueryBuilderSelectorInterface.
 * 
 * @see QueryBuilderSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class QueryBuilderSelector extends AbstractSelector implements QueryBuilderSelectorInterface
{
    use ResolvableNameSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'query builder';
    }
}