<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\FacadeSelectorInterface;

/**
 * Generic implementation of the FacadeSelectorInterface.
 * 
 * @see FacadeSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class FacadeSelector extends AbstractSelector implements FacadeSelectorInterface
{
    use ResolvableNameSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'facade';
    }
}