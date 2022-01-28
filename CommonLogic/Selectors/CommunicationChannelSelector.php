<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;

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
    use ResolvableNameSelectorTrait;
    
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