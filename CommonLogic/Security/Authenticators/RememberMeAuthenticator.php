<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Contexts\DataContext;
use exface\Core\CommonLogic\Security\AuthenticationToken\RememberMeAuthToken;
use exface\Core\Exceptions\Security\AuthenticationFailedError;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class RememberMeAuthenticator extends AbstractAuthenticator
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationProviderInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token) : AuthenticationTokenInterface
    {
        if (! ($token instanceof RememberMeAuthToken)) {
            throw new AuthenticationFailedError('Invalid token type!');
        }
        
        $sessionUserName = $this->getUsernameFromSession();
        
        if ($token->getUsername() === null && $sessionUserName !== null) {
            $token = new RememberMeAuthToken($sessionUserName, $token->getFacade());
        } elseif ($token->getUsername() === null || $token->getUsername() !== $sessionUserName) {
            throw new AuthenticationFailedError('Cannot authenticate user "' . $token->getUsername() . '" via remember-me.');
        }
        
        return $token;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::isAuthenticated()
     */
    public function isAuthenticated(AuthenticationTokenInterface $token) : bool
    {
        return $token->getUsername() !== $this->getUsernameFromSession();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token) : bool
    {
        return $token instanceof RememberMeAuthToken;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getNameDefault()
     */
    protected function getNameDefault() : string
    {
        return 'Remember me';
    }
    
    public function getTokenFromSession() : RememberMeAuthToken
    {
        return new RememberMeAuthToken($this->getUsernameFromSession());
    }
    
    public function setTokenInSession(AuthenticationTokenInterface $token) : RememberMeAuthenticator
    {
        return $this->getWorkbench()->getContext()->getScopeSession()->setSessionAuthToken($token);
    }
    
    protected function getUsernameFromSession() : ?string
    {
        return $this->getWorkbench()->getContext()->getScopeSession()->getSessionUsername();
    }
}