<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\CommonLogic\Workbench;

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
    
    public function __construct(Workbench $workbench, $selectorString)
    {
        $this->selectorString = $selectorString;
        $this->workbench = $workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
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
    public function toString()
    {
        return $this->selectorString;
    }
    
    public function __toString()
    {
        return $this->toString();
    }
}