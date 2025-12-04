<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\PrototypeSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\MutationPointSelectorInterface;
use exface\Core\Interfaces\Selectors\UxonSnippetSelectorInterface;

/**
 * Generic implementation of the UxonSnippetSelectorInterface
 * 
 * @see UxonSnippetSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class MutationPointSelector extends AbstractSelector implements MutationPointSelectorInterface
{
    use PrototypeSelectorTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'mutation point';
    }
}