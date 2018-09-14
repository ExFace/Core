<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\TemplateSelectorInterface;

/**
 * Generic implementation of the TemplateSelectorInterface.
 * 
 * @see TemplateSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class TemplateSelector extends AbstractSelector implements TemplateSelectorInterface
{
    use ResolvableNameSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'template';
    }
}