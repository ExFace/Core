<?php
namespace exface\Core\Events\Facades;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\FacadeEventInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Events\HttpRequestEventInterface;
use exface\Core\Interfaces\Facades\HttpMiddlewareBusInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;

/**
 * Event triggered after the owner (user) of an HTTP request was determined
 * 
 * @event exface.Core.Facades.OnHttpRequestAuthenticated
 * 
 * @author Andrej Kabachnik
 *
 */
class OnHttpRequestAuthenticatedEvent extends AbstractEvent implements FacadeEventInterface, HttpRequestEventInterface
{
    private $facade = null;
    
    private $request = null;
    
    private $token = null;
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param ServerRequestInterface $request
     * @param HttpMiddlewareBusInterface $middlewareBus
     */
    public function __construct(FacadeInterface $facade, ServerRequestInterface $request, AuthenticationTokenInterface $authenticatedToken)
    {
        $this->facade = $facade;
        $this->request = $request;
        $this->token = $authenticatedToken;
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
     * @return AuthenticationTokenInterface
     */
    public function getAuthenticatedToken() : AuthenticationTokenInterface
    {
        return $this->token;
    }
}