<?php
namespace exface\Core\Events\Security;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\FacadeEventInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * Event fired before authentication happens in a facade.
 *
 * @event exface.Core.Security.OnBeforeAuthentication
 *
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeAuthenticationEvent extends AbstractEvent implements FacadeEventInterface
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