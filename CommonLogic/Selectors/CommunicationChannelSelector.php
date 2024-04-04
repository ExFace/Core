<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;

/**
 * Generic implementation of the CommunicationChannelSelectorInterface.
 * 
 * @see CommunicationChannelSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class CommunicationChannelSelector extends AbstractSelector implements CommunicationChannelSelectorInterface
{
    use AliasSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'communication channel';
    }
}