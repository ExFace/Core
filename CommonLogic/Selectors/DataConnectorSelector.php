<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\Interfaces\Selectors\DataConnectorSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\PrototypeSelectorTrait;

/**
 * Generic implementation of the DataConnectorSelectorInterface.
 * 
 * @see DataConnectorSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataConnectorSelector extends AbstractSelector implements DataConnectorSelectorInterface
{
    use PrototypeSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'data connector';
    }
}