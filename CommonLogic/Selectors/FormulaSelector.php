<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\FormulaSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Generic implementation of the FormulaSelectorInterface.
 * 
 * @see FormulaSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class FormulaSelector extends AbstractSelector implements FormulaSelectorInterface
{
    use ResolvableNameSelectorTrait;
    
    private $defaultAppAlias = '';
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $selectorString
     */
    public function __construct(WorkbenchInterface $workbench, string $selectorString)
    {
        parent::__construct($workbench, $selectorString);
        if (! $this->isClassname() && ! $this->isFilepath()) {
            if (strpos($selectorString, AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER) === false) {
                $this->defaultAppAlias = 'exface.Core.';
            }
        }
    }
    
    public function toString() : string
    {
        return $this->defaultAppAlias . parent::toString();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'formula';
    }
}