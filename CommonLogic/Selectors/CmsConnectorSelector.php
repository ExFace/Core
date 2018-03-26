<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\CmsConnectorSelectorInterface;

/**
 * Generic implementation of the CmsConnectorSelectorInterface.
 * 
 * @see CmsConnectorSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class CmsConnectorSelector extends AbstractSelector implements CmsConnectorSelectorInterface
{
    use ResolvableNameSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'CMS connector';
    }
}