<?php
namespace exface\Core\Events\Facades;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\FacadeEventInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Events\HttpRequestEventInterface;

/**
 * Event triggered right before the handler of an HTTP request in a facade - after all middleware processing is done.
 * 
 * @event exface.Core.Facades.OnHttpRequestHandling
 * 
 * @author Andrej Kabachnik
 *
 */
class OnHttpRequestHandlingEvent extends AbstractEvent implements FacadeEventInterface, HttpRequestEventInterface
{
    private $facade = null;
    
    private $request = null;
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param ServerRequestInterface $request
     */
    public function __construct(FacadeInterface $facade, ServerRequestInterface $request)
    {
        $this->facade = $facade;
        $this->request = $request;
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
    public function getFacade() : FacadeInterface
    {
        return $this->facade;
    }
    
    /**
     * 
     * @return ServerRequestInterface
     */
    public function getRequest() : ServerRequestInterface
    {
        return $this->request;
    }
}