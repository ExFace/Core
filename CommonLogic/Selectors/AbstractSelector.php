<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Base class for all kinds of selectors
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractSelector implements SelectorInterface
{    
    private $selectorString = null;
    
    private $workbench = null;
    
    public function __construct(WorkbenchInterface $workbench, string $selectorString)
    {
        $this->selectorString = $selectorString;
        $this->workbench = $workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::toString()
     */
    public function toString() : string
    {
        return $this->selectorString;
    }
    
    public function __toString()
    {
        return $this->toString();
    }
}