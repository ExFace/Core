<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\PrototypeSelectorTrait;
use exface\Core\Interfaces\Selectors\AiConceptSelectorInterface;

/**
 * Generic implementation of the AiConceptSelectorInterface.
 * 
 * @see AiConceptSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class AiConceptSelector extends AbstractSelector implements AiConceptSelectorInterface
{
    use PrototypeSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'AI concept';
    }
}