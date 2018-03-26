<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\DataConnectorSelectorInterface;

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
    use ResolvableNameSelectorTrait;
    
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