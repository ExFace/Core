<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\ContextSelectorInterface;

/**
 * Generic implementation of the ContextSelectorInterface.
 * 
 * @see ContextSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class ContextSelector extends AbstractSelector implements ContextSelectorInterface
{
    use ResolvableNameSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'context';
    }
}