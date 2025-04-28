<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\UxonSnippetSelectorInterface;

/**
 * Generic implementation of the UxonSnippetSelectorInterface
 * 
 * @see UxonSnippetSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonSnippetSelector extends AbstractSelector implements UxonSnippetSelectorInterface
{
    use AliasSelectorTrait;

    use UidSelectorTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::isAlias()
     */
    public function isAlias() : bool
    {
        return $this->isUid() === false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'UXON snippet';
    }
}