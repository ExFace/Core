<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\AiAgentSelectorInterface;

/**
 * Generic implementation of the AiAgentSelectorInterface.
 * 
 * @see AiAgentSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class AiAgentSelector extends AbstractSelector implements AiAgentSelectorInterface
{
    use ResolvableNameSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'AI agent';
    }
}