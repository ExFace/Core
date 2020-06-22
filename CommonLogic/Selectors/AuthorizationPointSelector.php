<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\Interfaces\Selectors\AuthorizationPointSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\PrototypeSelectorTrait;

/**
 * Default implementation of the AuthorizationPointSelectorInterface.
 * 
 * @see UserRoleSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class AuthorizationPointSelector extends AbstractSelector implements AuthorizationPointSelectorInterface
{
    use PrototypeSelectorTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType(): string
    {
        return 'Authorization point';
    }
}