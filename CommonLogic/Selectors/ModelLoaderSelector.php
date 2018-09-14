<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\ModelLoaderSelectorInterface;

/**
 * Generic implementation of the ModelLoaderSelectorInterface.
 * 
 * @see ModelLoaderSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class ModelLoaderSelector extends AbstractSelector implements ModelLoaderSelectorInterface
{
    use ResolvableNameSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'metamodel loader';
    }
}