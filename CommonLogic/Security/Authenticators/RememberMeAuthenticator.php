<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\CommonLogic\Security\AuthenticationToken\RememberMeAuthToken;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\CommonLogic\Security\AuthenticationToken\AnonymousAuthToken;
use exface\Core\Factories\UserFactory;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\DataTypes\DateTimeDataType;
use function GuzzleHttp\json_encode;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class RememberMeAuthenticator extends AbstractAuthenticator
{    
    CONST SESSION_DATA_DELIMITER = ':';    
    
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
        
        $sessionData = $this->getSessionData();
        
        $sessionUserName = $sessionData['username'];
        
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
        if (hash_equals($this->generateSessionDataHash($user->getUsername(), $sessionData['expires'], $user->getPassword()), $sessionData['hash']) === false) {
            throw new AuthenticationFailedError($this, 'Cannot authenticate user "' . $token->getUsername() . '" via remember-me. Hash is invalid!');
        }
        if ($sessionData['expires'] < time()) {
            throw new AuthenticationFailedError($this, 'Cannot authenticate user "' . $token->getUsername() . '" via remember-me. Session login time expired!');
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
     * Set the time in seconds a user should stay logged in after he did log in. Default is 1 week (604800 seconds).
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
    
    /**
     * Save the user data, matching this token, in the session.
     * 
     * @param AuthenticationTokenInterface $token
     * @return RememberMeAuthenticator
     */
    public function saveSessionData(AuthenticationTokenInterface $token) : RememberMeAuthenticator
    {
        if ($token->isAnonymous()) {
            $this->getWorkbench()->getContext()->getScopeSession()->setSessionUserData(NULL);
            return $this;
        }
        $this->getWorkbench()->getContext()->getScopeSession()->setSessionUserData($this->getSessionDataString($token));
        return $this;        
    }
    
    protected function getSessionData() : ?array
    {
        $dataString = $this->getWorkbench()->getContext()->getScopeSession()->getSessionUserData();
        if ($dataString === null) {
            return $dataString;
        }
        $data = json_decode($dataString, true);
        return $data;
    }
    
    /**
     * 
     * @param AuthenticationTokenInterface $token
     * @return string
     */
    protected function getSessionDataString (AuthenticationTokenInterface $token) : string
    {
        $data = [];
        $oldSessionData = $this->getSessionData();
        if ($oldSessionData !== null && $oldSessionData['username'] === $token->getUsername()) {
            $expires = $oldSessionData['expires'];
        } else {            
            $expires = time() + $this->getLiftetime();
        }
        $user = $this->getWorkbench()->getSecurity()->getUser($token);
        $data['username'] = $user->getUsername();
        $data['expires'] = $expires;
        $data['hash'] = $this->generateSessionDataHash($user->getUsername(), $expires, $user->getPassword());
        return json_encode($data);
    }
    
    /**
     * 
     * @param string $username
     * @param int $expires
     * @param string $password
     * @return string
     */
    protected function generateSessionDataHash (string $username, int $expires, string $password) : string
    {
        return hash_hmac('sha256', $username . self::SESSION_DATA_DELIMITER . $expires . self::SESSION_DATA_DELIMITER . $password, $this->getSecret);
    }
    
    /**
     * 
     * @return string
     */
    protected function getSecret() : string
    {
        $this->getWorkbench()->getSecret();
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