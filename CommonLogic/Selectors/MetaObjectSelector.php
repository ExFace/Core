<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;

/**
 * Default implementation of the MetaObjectSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class MetaObjectSelector extends AbstractSelector implements MetaObjectSelectorInterface
{
    use AliasSelectorTrait;
    use UidSelectorTrait;  
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::isAlias()
     */
    public function isAlias()
    {
        return $this->isUid() === false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'meta object';
    }
}