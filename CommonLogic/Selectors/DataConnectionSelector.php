<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\Interfaces\Selectors\DataConnectionSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorWithOptionalNamespaceTrait;

/**
 * Generic implementation of the DataConnectionSelectorInterface.
 * 
 * @see DataConnectionSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataConnectionSelector extends AbstractSelector implements DataConnectionSelectorInterface
{
    use AliasSelectorWithOptionalNamespaceTrait;
    use UidSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'data connection';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::isAlias()
     */
    public function isAlias()
    {
        return $this->isUid() === false;
    }
}