<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\AttributeGroupSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;

/**
 * Default implementation of the UserRoleSelectorInterface.
 * 
 * @see UserRoleSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class AttributeGroupSelector extends AbstractSelector implements AttributeGroupSelectorInterface
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
        return 'Attribute group';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\UserRoleSelectorInterface::isGlobalRoleAuthenticated()
     */
    public function isBuiltInGroup() : bool
    {
        return mb_substr($this->__toString(), 0, 1) === '~';
    }
}