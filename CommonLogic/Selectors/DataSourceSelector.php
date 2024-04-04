<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\DataSourceSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;

/**
 * Generic implementation of the DataSourceSelectorInterface.
 * 
 * @see DataSourceSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSourceSelector extends AbstractSelector implements DataSourceSelectorInterface
{
    use UidSelectorTrait;
    
    use AliasSelectorTrait;
    
    const METAMODEL_SOURCE_ALIAS = 'METAMODEL_SOURCE';
    
    const METAMODEL_SOURCE_UID = '0x32000000000000000000000000000000';
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'data source';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see AliasSelectorTrait::isAlias()
     */
    public function isAlias()
    {
        return ! $this->isUid();
    }
}