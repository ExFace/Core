<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;

/**
 * Generic implementation of the DataTypeSelectorInterface.
 * 
 * @see DataTypeSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataTypeSelector extends AbstractSelector implements DataTypeSelectorInterface
{
    use ResolvableNameSelectorTrait {
        isAlias as isAliasViaTrait;
    }
    use UidSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'datatype';
    }
    
    public function isAlias()
    {
        if ($this->isUid()) {
            return true;
        }
        return $this->isAliasViaTrait();
    }
}