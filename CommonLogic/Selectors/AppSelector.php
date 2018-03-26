<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;

/**
 * Generic implementation of the AppSelectorInterface
 * 
 * @see ActionSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class AppSelector extends AbstractSelector implements AppSelectorInterface
{
    use ResolvableNameSelectorTrait;
    use UidSelectorTrait;
    
    public function getClassnameOfDefaultPrototype()
    {
        return '\\exface\\Core\\CommonLogic\\Model\\App';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'app';
    }
}