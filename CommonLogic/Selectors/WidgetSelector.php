<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\WidgetSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;

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
    
    /**
     * Returns FALSE for selectors for custom (non-core) widgets.
     * 
     * @return bool
     */
    public function isCoreWidget() : bool
    {
        return $this->isAlias() === true && strpos($this->toString(), AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER) === false;
    }
}