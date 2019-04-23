<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\WidgetSelectorInterface;

/**
 * Generic implementation of the WidgetSelectorInterface.
 * 
 * @see WidgetSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class WidgetSelector extends AbstractSelector implements WidgetSelectorInterface
{
    use ResolvableNameSelectorTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'widget';
    }
}