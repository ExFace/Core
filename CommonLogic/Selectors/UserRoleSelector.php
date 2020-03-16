<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\UserRoleSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;

/**
 * Default implementation of the UserRoleSelectorInterface.
 * 
 * @see UserRoleSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class UserRoleSelector extends AbstractSelector implements UserRoleSelectorInterface
{
    use UidSelectorTrait;
    
    use AliasSelectorTrait;
    
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
    public function getComponentType(): string
    {
        return 'User role';
    }
}