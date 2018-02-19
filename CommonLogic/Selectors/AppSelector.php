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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\PrototypeSelectorInterface::getPrototypeSubfolder()
     */
    public function getPrototypeSubfolder()
    {
        return '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait::getPrototypeSubfolder()
     */
    protected function getPrototypeClassnameSuffix()
    {
        return 'App';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait::validateAliasParts()
     */
    protected function validateAliasParts(array $parts)
    {
        return count($parts) < 2 ? false : true;
    }
    
    public function getClassnameOfDefaultPrototype()
    {
        return '\\exface\\Core\\CommonLogic\\Model\\App';
    }
}