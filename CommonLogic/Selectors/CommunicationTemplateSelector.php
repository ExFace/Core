<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\Selectors\CommunicationTemplateSelectorInterface;

/**
 * Generic implementation of the CommunicationTemplateSelectorInterface.
 * 
 * @see CommunicationTemplateSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class CommunicationTemplateSelector extends AbstractSelector implements CommunicationTemplateSelectorInterface
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
        return 'communication template';
    }
}