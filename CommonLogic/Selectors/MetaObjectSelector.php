<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;
use exface\Core\Exceptions\Selectors\SelectorTypeInvalidError;

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
    
    public function getAliasWithNamespace()
    {
        switch (true) {
            case $this->isAlias() :
                return $this->toString();
            case $this->isUid() :
            default :
                throw new SelectorTypeInvalidError('Cannot convert "' . $this->toString() . '" into meta object alias!');
        }
    }   
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::isAlias()
     */
    public function isAlias()
    {
        return $this->isUid() ? false : true;
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