<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\PrototypeSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\CommunicationTemplateSelectorInterface;
use exface\Core\Interfaces\Selectors\PermalinkSelectorInterface;

/**
 * Generic implementation of the CommunicationTemplateSelectorInterface.
 * 
 * @see CommunicationTemplateSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class PermalinkSelector extends AbstractSelector implements PermalinkSelectorInterface
{
    use AliasSelectorTrait;
    use PrototypeSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'permalink';
    }
}