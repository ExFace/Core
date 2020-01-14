<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;

/**
 * Generic implementation of the UserSelectorInterface.
 * 
 * @see WidgetSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class UserSelector extends AbstractSelector implements UserSelectorInterface
{
    use UidSelectorTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\UserSelectorInterface::isUsername()
     */
    public function isUsername() : bool
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
        return 'User';
    }

}