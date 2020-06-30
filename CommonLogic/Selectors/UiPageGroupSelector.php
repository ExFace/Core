<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\UiPageGroupSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;

/**
 * Default implementation of the UiPageGroupSelectorInterface.
 * 
 * @see UiPageGroupSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class UiPageGroupSelector extends AbstractSelector implements UiPageGroupSelectorInterface
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
        return 'UI page group';
    }
}