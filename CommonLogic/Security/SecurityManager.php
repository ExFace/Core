<?php
namespace exface\Core\CommonLogic\Security;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\Security\SecurityManagerInterface;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\CommonLogic\Security\AuthenticationToken\AnonymousAuthToken;
use exface\Core\Interfaces\Security\AuthenticatorInterface;
use exface\Core\CommonLogic\Security\Authenticators\SymfonyAuthenticator;

/**
 * Default implementation of the SecurityManagerInterface.
 * 
 * @author Andrej Kabachnik
 *
 */
class SecurityManager implements SecurityManagerInterface
{
    private $workbench;
    
    private $authenticators = null;
    
    private $authenticatedToken = null;

    /**
     *
     * @param \exface\Core\CommonLogic\Workbench $exface            
     */
    function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
        
        // Initialize all authenticators to give them the option to register
        // event listeners (e.g. for the exface.Core.Security.OnBeforeAuthentication 
        // event).
        $this->initAuthenticators();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token): AuthenticationTokenInterface
    {      
        $err = new AuthenticationFailedError('Authentication failed!');
        foreach ($this->getAuthenticators() as $authenticator) {
            if ($authenticator->isSupported($token) === false) {
                continue;
            }
            try {
                $authenticated = $authenticator->authenticate($token);
                $this->storeAuthenticatedToken($authenticated);
                return $authenticated;
            } catch (AuthenticationException $e) {
                $err->addAuthenticatorError($authenticator, new AuthenticationFailedError($e->getMessage(), null, $e));
            }
        }
        
        if ($token->isAnonymous() === true) {
            $this->storeAuthenticatedToken($token);
            return $token;
        }
        
        throw $err;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::isAuthenticated()
     */
    public function isAuthenticated(AuthenticationTokenInterface $token) : bool
    {
        foreach ($this->getAuthenticators() as $authenticator) {
            if ($authenticator->isSupported($token) === true && $authenticator->isAuthenticated($token) === true) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::getAuthenticatedToken()
     */
    public function getAuthenticatedToken() : AuthenticationTokenInterface
    {
        if ($this->authenticatedToken === null) {
            $this->authenticatedToken = new AnonymousAuthToken($this->getWorkbench());
        }
        return $this->authenticatedToken;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::getAuthenticatedUser()
     */
    public function getAuthenticatedUser() : UserInterface
    {
        return $this->getAuthenticatedToken()->getUser();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * @param AuthenticationTokenInterface $token
     * @return self
     */
    protected function storeAuthenticatedToken(AuthenticationTokenInterface $token) : self
    {
        $this->authenticatedToken = $token;
        return $this;
    }
    
    /**
     * 
     * @return AuthenticatorInterface[]
     */
    protected function getAuthenticators() : array
    {
        return $this->authenticators;
    }
    
    /**
     * 
     * @return self
     */
    protected function initAuthenticators() : self
    {
        $this->authenticators = [
            new SymfonyAuthenticator($this->getWorkbench())
        ];
        foreach ($this->getWorkbench()->getConfig()->getOption('SECURITY.AUTHENTICATORS') as $class) {
            $this->authenticators[] = new $class($this->getWorkbench());
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::getName()
     */
    public function getName(): string
    {
        return 'Security Manager';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token): bool
    {
        return true;
    }

}
?>