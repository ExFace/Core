<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\PrototypeSelectorTrait;
use exface\Core\Interfaces\Selectors\CommunicationMessageSelectorInterface;

/**
 * Generic implementation of the DataConnectorSelectorInterface.
 * 
 * @see DataConnectorSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class CommunicationMessageSelector extends AbstractSelector implements CommunicationMessageSelectorInterface
{
    use PrototypeSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'message type';
    }
}