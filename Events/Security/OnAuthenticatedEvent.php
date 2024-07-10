<?php
namespace exface\Core\Events\Security;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Security\AuthenticationProviderInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Event fired after a user was authenticated by the workbench security.
 *
 * @event exface.Core.Security.OnAuthenticated
 *
 * @author Andrej Kabachnik
 *        
 */
class OnAuthenticatedEvent extends AbstractEvent
{
    private $authenticationProvider = null;
    
    private $token = null;
    
    private $workbench = null;
    
    private $logbook = null;
    
    public function __construct(WorkbenchInterface $workbench, AuthenticationTokenInterface $token, AuthenticationProviderInterface $provider = null, LogBookInterface $logbook = null)
    {
        $this->workbench = $workbench;
        $this->authenticationProvider = $provider;
        $this->token = $token;
    }
    
    /**
     * Returns the token linked to that event.
     * 
     * @return AuthenticationTokenInterface
     */
    public function getToken() : AuthenticationTokenInterface
    {
        return $this->token;
    }
    
    /**
     * Returns authentication provider linked to that event.
     * 
     * @return AuthenticationProviderInterface|NULL
     */
    public function getAuthenticationProvider() : ?AuthenticationProviderInterface
    {
        return $this->authenticationProvider;
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
     * @return LogBookInterface|NULL
     */
    public function getLogbook() : ?LogBookInterface
    {
        return $this->logbook;
    }
}