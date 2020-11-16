<?php
namespace exface\Core\Events\Security;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Security\AuthenticationProviderInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Exceptions\AuthenticationExceptionInterface;

/**
 * Event fired after an authentication attempt failed in an authenticator.
 * 
 * Note: this even ist fired regardless of wether the authentication exception was
 * caught or not. It just happens whenever an auth provider fails to handle a token!
 *
 * @event exface.Core.Security.OnAuthenticationFailed
 *
 * @author Andrej Kabachnik
 *        
 */
class OnAuthenticationFailedEvent extends AbstractEvent
{
    private $exception = null;
    
    private $workbench = null;
    
    public function __construct(WorkbenchInterface $workbench, AuthenticationExceptionInterface $error)
    {
        $this->workbench = $workbench;
        $this->exception = $error;
    }
    
    /**
     * Returns authentication provider linked to that event.
     * 
     * @return AuthenticationProviderInterface|NULL
     */
    public function getAuthenticationProvider() : AuthenticationProviderInterface
    {
        return $this->exception->getAuthenticationProvider();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench() : WorkbenchInterface
    {
        return $this->workbench;
    }
    
    /**
     * 
     * @return AuthenticationExceptionInterface
     */
    public function getException() : AuthenticationExceptionInterface
    {
        return $this->exception;
    }
}