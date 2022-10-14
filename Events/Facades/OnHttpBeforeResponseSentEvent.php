<?php
namespace exface\Core\Events\Facades;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\FacadeEventInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Events\HttpRequestEventInterface;
use exface\Core\Interfaces\Facades\HttpMiddlewareBusInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Interfaces\Events\HttpResponseEventInterface;

/**
 * Event triggered right before an HTTP facade sends the response to the browser
 * 
 * @event exface.Core.Facades.OnHttpBeforeResponseSent
 * 
 * @author Andrej Kabachnik
 *
 */
class OnHttpBeforeResponseSentEvent extends AbstractEvent implements FacadeEventInterface, HttpRequestEventInterface, HttpResponseEventInterface
{
    private $facade = null;
    
    private $response = null;
    
    private $request = null;
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param ServerRequestInterface $request
     * @param HttpMiddlewareBusInterface $middlewareBus
     */
    public function __construct(FacadeInterface $facade, ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->facade = $facade;
        $this->request = $request;
        $this->response = $response;
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\HttpResponseEventInterface::getResponse()
     */
    public function getResponse() : ResponseInterface
    {
        return $this->response;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\HttpRequestEventInterface::getRequest()
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}