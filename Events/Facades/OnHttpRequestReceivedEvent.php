<?php
namespace exface\Core\Events\Facades;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\FacadeEventInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Events\HttpRequestEventInterface;
use exface\Core\Interfaces\Facades\HttpMiddlewareBusInterface;

/**
 * Event triggered when an HTTP facade receives a request, but before the request processing was begun
 * 
 * @event exface.Core.Facades.OnHttpRequestReceived
 * 
 * @author Andrej Kabachnik
 *
 */
class OnHttpRequestReceivedEvent extends AbstractEvent implements FacadeEventInterface, HttpRequestEventInterface
{
    private $facade = null;
    
    private $request = null;
    
    private $bus = null;
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param ServerRequestInterface $request
     * @param HttpMiddlewareBusInterface $middlewareBus
     */
    public function __construct(FacadeInterface $facade, ServerRequestInterface $request, HttpMiddlewareBusInterface $middlewareBus = null)
    {
        $this->facade = $facade;
        $this->request = $request;
        $this->bus = $middlewareBus;
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
    
    /**
     * 
     * @return HttpMiddlewareBusInterface
     */
    public function getMiddlewareBus() : HttpMiddlewareBusInterface
    {
        return $this->bus;
    }
}