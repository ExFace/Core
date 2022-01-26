<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\Interfaces\Selectors\DataConnectionSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;

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
    const METAMODEL_CONNECTION_ALIAS = 'exface.Core.METAMODEL_DB';
    
    const METAMODEL_CONNECTION_UID = '0x11ea72c00f0fadeca3480205857feb80';
    
    use AliasSelectorTrait;
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