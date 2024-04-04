<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\PWASelectorInterface;

/**
 * Generic implementation of the PWASelectorInterface.
 * 
 * @see CommunicationTemplateSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class PWASelector extends AbstractSelector implements PWASelectorInterface
{
    use AliasSelectorTrait;
    
    use UidSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'PWA';
    }
}