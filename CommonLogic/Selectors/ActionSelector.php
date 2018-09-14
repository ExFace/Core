<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;

/**
 * Generic implementation of the ActionSelectorInterface
 * 
 * @see ActionSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class ActionSelector extends AbstractSelector implements ActionSelectorInterface
{
    use ResolvableNameSelectorTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'action';
    }
}