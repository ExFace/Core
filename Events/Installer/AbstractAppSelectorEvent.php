<?php
namespace exface\Core\Events\Installer;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractAppSelectorEvent extends AbstractEvent
{
    private $selector = null;
    
    /**
     * 
     * @param AppSelectorInterface $selector
     */
    public function __construct(AppSelectorInterface $selector)
    {
        $this->selector = $selector;
    }

    
    public function getAppSelector() : AppSelectorInterface
    {
        return $this->selector;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->selector->getWorkbench();
    }
}