<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\CommonLogic\Security\AuthenticationToken\RememberMeAuthToken;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\DataTypes\EncryptedDataType;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Events\Security\OnAuthenticatedEvent;
use exface\Core\Interfaces\Security\AuthenticationProviderInterface;
use exface\Core\Interfaces\Security\AuthenticatorInterface;
use exface\Core\Exceptions\EncryptionError;
use exface\Core\Facades\ConsoleFacade;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;

/**
 * Stores user data in the session context scope and attempts to re-authenticate the user with every request.
 * 
 * Every time a user is authenticated by an authenticator, the `RememberMeAuthenticator` stores
 * the username and some security information in the session context scope for as long as the
 * token lifetime of the active authenticator. The user data is encrypted. 
 * 
 * Every time the `RememberMeAuthenticator` attempts an authentication, it will load the stored
 * user data, decrypt it, do some validation (like checking if the user still exists) and
 * authenticate that user again.
 * 
 * @author Andrej Kabachnik
 *
 */
class RememberMeAuthenticator extends AbstractAuthenticator
{    
    CONST SESSION_DATA_DELIMITER = ':';
    
    private $sessionData = null;
    private $sessionDataEncrypted = null;
    
    /**
     *
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        parent::__construct($workbench);
        $workbench->eventManager()->addListener(OnAuthenticatedEvent::getEventName(), [$this, 'handleOnAuthenticated']);
    }
    
    /**
     * 
     * @param OnAuthenticatedEvent $event
     */
    public function handleOnAuthenticated(OnAuthenticatedEvent $event)
    {
        if ($event->getAuthenticationProvider() instanceof RememberMeAuthenticator) {
            return;
        }
        
        // There are no sessions in CLI, so also no remmeber-me
        if (ConsoleFacade::isPhpScriptRunInCli()) {
            return;
        }
        
        $this->saveSessionData($event->getToken(), $event->getAuthenticationProvider());
        return;
    }
    
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
        
        // There are no sessions in CLI, so also no remmeber-me
        if (ConsoleFacade::isPhpScriptRunInCli()) {
            throw new AuthenticationFailedError($this, 'No Remember-Me possible on the command line!');
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getNameDefault()
     */
    protected function getNameDefault() : string
    {
        return 'Remember me';
    }
    
    /**
     * 
     * @param AuthenticationTokenInterface $token
     * @param AuthenticationProviderInterface $provider
     * @return RememberMeAuthenticator
     */
    protected function saveSessionData(AuthenticationTokenInterface $token, AuthenticationProviderInterface $provider = null) : RememberMeAuthenticator
    {
        $sessionScope = $this->getWorkbench()->getContext()->getScopeSession();
        if ($token->isAnonymous()) {
            if ($this->getWorkbench()->getContext()->getScopeSession()->getSessionUserData()) {
                $sessionScope->clearSessionData();
            }
        } else {
            $sessionScope->setSessionUserData($this->createSessionDataString($token, $provider));
        }
        $this->sessionData = null;
        $this->sessionDataEncrypted = null;
        return $this;        
    }
    
    /**
     * 
     * @return array|NULL
     */
    protected function getSessionData() : ?array
    {
        $dataString = $this->getWorkbench()->getContext()->getScopeSession()->getSessionUserData();
        
        // If already decrypted and the current session still has the same encrypted string,
        // used the cached decrypted data to avoid another decryption run
        if ($this->sessionDataEncrypted === $dataString && $this->sessionData !== null ) {
            return $this->sessionData;
        }
        
        $this->sessionDataEncrypted = $dataString;
        
        if ($dataString === null || $dataString === '') {
            return null;
        }
        
        try {
            $dataString = EncryptedDataType::decrypt($this->getSecret(), $dataString);
        } catch (EncryptionError $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            return null;
        }
        $data = json_decode($dataString, true);
        
        // Cache decrypted data
        $this->sessionData = $data;
        
        return $data;
    }
    
    /**
     * 
     * @param AuthenticationTokenInterface $token
     * @return string
     */
    protected function createSessionDataString(AuthenticationTokenInterface $token, AuthenticationProviderInterface $provider) : string
    {
        $data = [];
        $oldSessionData = $this->getSessionData();
        if ($oldSessionData !== null && $oldSessionData['username'] === $token->getUsername()) {
            $expires = $oldSessionData['expires'];
        } else {
            if (! ($provider instanceof AuthenticatorInterface) || ($lifetime = $provider->getTokenLifetime($token)) === null) {
                $lifetime = $this->getTokenLifetime($token);
            }
            
            // If the lifetime is 0, we should not store any information at all!
            if ($lifetime === 0) {
                return '';
            }
            
            $expires = time() + $lifetime;
        }
        $user = $this->getWorkbench()->getSecurity()->getUser($token);
        $data['username'] = $user->getUsername();
        $data['expires'] = $expires;
        $data['hash'] = $this->generateSessionDataHash($user->getUsername(), $expires, $user->getPassword());
        $string = json_encode($data);
        $string = EncryptedDataType::encrypt($this->getSecret(), $string);
        return $string;
    }
    
    /**
     * 
     * @param string $username
     * @param int $expires
     * @param string $password
     * @return string
     */
    protected function generateSessionDataHash (string $username, int $expires, string $password = null) : string
    {
        if ($password === null) {
            $password = '';
        }
        return hash_hmac('sha256', $username . self::SESSION_DATA_DELIMITER . $expires . self::SESSION_DATA_DELIMITER . $password, $this->getSecret());
    }
    
    /**
     * 
     * @return string
     */
    protected function getSecret() : string
    {
        return EncryptedDataType::getSecret($this->getWorkbench());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getTokenLifetime()
     */
    public function getTokenLifetime(AuthenticationTokenInterface $token) : ?int
    {
        if ($this->lifetime === null) {
            return 604800;
        }
        return $this->lifetime;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::createLoginWidget()
     */
    public function createLoginWidget(iContainOtherWidgets $container) : iContainOtherWidgets
    {
        return $container;
    }
}