<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\BehaviorSelectorInterface;

/**
 * Generic implementation of the BehaviorSelectorInterface.
 * 
 * @see BehaviorSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class BehaviorSelector extends AbstractSelector implements BehaviorSelectorInterface
{
    use ResolvableNameSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'behavior';
    }
}