<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\UserRoleSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorWithOptionalNamespaceTrait;

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
    
    const AUTHENTICATED_USER_ROLE_OID = '0x11ea6fa3cab9a380a3480205857feb80';
    
    const AUTHENTICATED_USER_ROLE_ALIAS = 'exface.Core.AUTHENTICATED';
    
    use UidSelectorTrait;
    
    use AliasSelectorWithOptionalNamespaceTrait;
    
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