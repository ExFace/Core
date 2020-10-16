<?php
namespace exface\Core\Events\Facades;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\FacadeEventInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * Event triggered when a facade was created.
 * 
 * @event exface.Core.Facades.OnFacadeInit
 * 
 * @author Andrej Kabachnik
 *
 */
class OnFacadeInitEvent extends AbstractEvent implements FacadeEventInterface
{
    private $facade = null;
    
    public function __construct(FacadeInterface $facade)
    {
        $this->facade = $facade;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->facade->getWorkbench();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\FacadeEventInterface::getFacade()
     */
    public function getFacade(): FacadeInterface
    {
        return $this->facade;
    }
}