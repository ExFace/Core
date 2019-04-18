<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\DataSourceSelectorInterface;

/**
 * Generic implementation of the DataSourceSelectorInterface.
 * 
 * @see DataSourceSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSourceSelector extends AbstractSelector implements DataSourceSelectorInterface
{
    use UidSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'data source';
    }
}