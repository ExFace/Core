<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\CommonLogic\Security\AuthenticationToken\RememberMeAuthToken;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\CommonLogic\Security\AuthenticationToken\AnonymousAuthToken;
use exface\Core\Factories\UserFactory;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use exface\Core\Exceptions\UserNotFoundError;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class RememberMeAuthenticator extends AbstractAuthenticator
{    
    
    private $lifetime = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationProviderInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token) : AuthenticationTokenInterface
    {
        if (! ($token instanceof RememberMeAuthToken)) {
            throw new AuthenticationFailedError($this, 'Invalid token type!');
        }
        
        $sessionUserName = $this->getUsernameFromSession();
        
        if ($token->getUsername() === null && $sessionUserName !== null) {
            $token = new RememberMeAuthToken($sessionUserName, $token->getFacade());
        } elseif ($token->getUsername() === null || $token->getUsername() !== $sessionUserName) {
            throw new AuthenticationFailedError($this, 'Cannot authenticate user "' . $token->getUsername() . '" via remember-me.');
        }
        $user = $this->getWorkbench()->getSecurity()->getUser($token);
        if ($user->hasModel() === false) {
            //return new AnonymousAuthToken($this->getWorkbench());
            throw new AuthenticationFailedError($this, 'Cannot authenticate user "' . $token->getUsername() . '" via remember-me. User does not exist!');
        }
        if  ($user->isDisabled()) {
            throw new AuthenticationFailedError($this, 'Cannot authenticate user "' . $token->getUsername() . '" via remember-me. User is disabled!');
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
     * Set the time in secodns a user should stay logged in after he did log in. Default is 1 week (604800 seconds).
     * 
     * @uxon-property liftetime_seconds
     * @uxon-type integer
     * 
     * @param int $seconds
     * @return RememberMeAuthenticator
     */
    public function setLifetimeSeconds (int $seconds) : RememberMeAuthenticator
    {
        $this->lifetime = $seconds;
        return $this;
    }
    
    /**
     * 
     * @return int
     */
    protected function getLiftetime() : int
    {
        if ($this->lifetime === null) {
            return 604800;
        }
        return $this->lifetime;
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