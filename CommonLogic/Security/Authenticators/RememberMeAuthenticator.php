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
use exface\Core\CommonLogic\Security\AuthenticationToken\ExpiredAuthToken;
use exface\Core\Events\Workbench\OnBeforeStopEvent;
use exface\Core\Events\Facades\OnHttpBeforeResponseSentEvent;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\CommonLogic\Security\AuthenticationToken\AnonymousAuthToken;
use exface\Core\Exceptions\Security\AuthenticationExpiredError;
use exface\Core\Interfaces\UserInterface;

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
    const SESSION_DATA_DELIMITER = ':';
    
    const SESSION_DATA_USERNAME = 'username';
    
    const SESSION_DATA_PASSWORD = 'password';
    
    const SESSION_DATA_STARTED = 'started';
    
    const SESSION_DATA_EXPIRES = 'expires';
    
    const SESSION_DATA_REFRESH_INTERVAL = 'refresh';
    
    const SESSION_DATA_HASH = 'hash';
    
    private $sessionData = null;
    
    private $sessionDataEncrypted = null;
    
    private $expiredToken = null;
    
    /**
     *
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        parent::__construct($workbench);
        $workbench->eventManager()->addListener(OnAuthenticatedEvent::getEventName(), [$this, 'onAuthenticatedSaveSessionData']);
    }
    
    /**
     * 
     * @param OnAuthenticatedEvent $event
     */
    public function onAuthenticatedSaveSessionData(OnAuthenticatedEvent $event)
    {
        if ($event->getAuthenticationProvider() instanceof RememberMeAuthenticator) {
            return;
        }
        
        $this->getWorkbench()->getLogger()->debug('Remember-me authenticator: detected authentication of "' . $event->getToken()->getUsername() . '"');
        
        // There are no sessions in CLI, so also no remmeber-me
        if (ConsoleFacade::isPhpScriptRunInCli()) {
            $this->getWorkbench()->getLogger()->debug('Remember-me authenticator: disabled in CLI mode');
            return;
        }
        $this->saveSessionData($event->getToken(), $event->getAuthenticationProvider());
        return;
    }
    
    /**
     * 
     * @param EventInterface $event
     * @throws AuthenticationFailedError
     */
    public function onBeforeStopLogout(EventInterface $event)
    {
        if ($this->expiredToken !== null) {
            $currentToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
            $expiredToken = $this->expiredToken;
            $this->expiredToken = null;
            if ($currentToken === $expiredToken) {
                $this->getWorkbench()->getLogger()->debug('Remember-me authenticator: logging out user "' . $expiredToken->getUsername() . '" because token expired!');
                $this->saveSessionData(new AnonymousAuthToken($event->getWorkbench()));
                throw new AuthenticationExpiredError($this, 'Your session has expired. Please re-login!');
            } else {
                $this->getWorkbench()->getLogger()->debug('Remember-me authenticator: NOT logging out user "' . $expiredToken->getUsername() . '" because active token changed in the meantime!');
            }
        }
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
        $sessionUserName = $sessionData[self::SESSION_DATA_USERNAME] ?? null;
        $logger = $this->getWorkbench()->getLogger();
        
        if (empty($sessionData)) {
            $logger->debug('Remember-me authenticator: no data found in session "' . $this->getWorkbench()->getContext()->getScopeSession()->getScopeId() . '".');
        } else {
            $logger->debug('Remember-me authenticator: found user "' . $sessionUserName . '" in session "' . $this->getWorkbench()->getContext()->getScopeSession()->getScopeId() . '".');
        }
        
        if ($token->getUsername() === null && $sessionUserName !== null) {
            $token = new RememberMeAuthToken(
                $sessionUserName, 
                $token->getFacade(), 
                $sessionData[self::SESSION_DATA_STARTED],
                $sessionData[self::SESSION_DATA_EXPIRES],
                $sessionData[self::SESSION_DATA_REFRESH_INTERVAL]
            );
        } elseif ($token->getUsername() === null || $token->getUsername() !== $sessionUserName) {
            throw new AuthenticationFailedError($this, 'Cannot authenticate user "' . $token->getUsername() . '" via remember-me. User name does not match session!');
        }
        $logger->debug('Remember-me authenticator: user "' . $sessionUserName . '" still authenticated');
        $user = $this->getWorkbench()->getSecurity()->getUser($token);
        if ($user->hasModel() === false) {
            throw new AuthenticationFailedError($this, 'Cannot authenticate user "' . $token->getUsername() . '" via remember-me. User does not exist!');
        }
        if ($user->isDisabled()) {
            throw new AuthenticationFailedError($this, 'Cannot authenticate user "' . $token->getUsername() . '" via remember-me. User is disabled!');
        }        
        if (false === hash_equals($this->generateSessionDataHash($user, $sessionData), $sessionData[self::SESSION_DATA_HASH])) {
            throw new AuthenticationFailedError($this, 'Cannot authenticate user "' . $token->getUsername() . '" via remember-me. Hash is invalid!');
        }
        
        if ($token instanceof RememberMeAuthToken) {
            // If the token has expired, wrap it in ExpiredAuthToken and make sure the user is logged out at the end of the 
            // current request
            // If the token is still OK, but is ready to be refreshed, refresh the session data, but leave the token as it is.
            if ($token->isExpired()) {
                $token = new ExpiredAuthToken($token);
                $this->expiredToken = $token;
                // Allow the request to do its job, so the user will not loose data just because the token timed out, but remember
                // to clear the session when the workbench stops.
                $this->getWorkbench()->eventManager()->addListener(OnBeforeStopEvent::getEventName(), [$this, 'onBeforeStopLogout']);
                // In case of HTTP requests, clear the session a little earlier - before the response is sent, so that the response
                // will already include the authentication error. If the response will be OK, the user will be able to start another
                // request, which might also include data, that will get lost then.
                $this->getWorkbench()->eventManager()->addListener(OnHttpBeforeResponseSentEvent::getEventName(), [$this, 'onBeforeStopLogout']);
            } elseif ($token->isRefreshDue()) {
                $this->saveSessionData($token);
            }
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
    protected function createSessionDataString(AuthenticationTokenInterface $token, AuthenticationProviderInterface $provider = null) : string
    {
        $data = [];
        $oldSessionData = $this->getSessionData();
        $logger = $this->getWorkbench()->getLogger();
        
        switch (true) {
            case $provider !== null: 
                $lifetime = $this->getTokenLifetimeDefault($token, $provider);
                $refreshAfter = $this->getTokenRefreshIntervalDefault($provider);
                break;
            case $token instanceof RememberMeAuthToken:
                $lifetime = $token->getLifetime();
                $refreshAfter = $token->getRefreshInterval();
                break;
            default:
                $lifetime = $this->getTokenLifetime($token);
                $refreshAfter = 0;
        }
        
        $curTime = time();
        if ($oldSessionData !== null && $oldSessionData[self::SESSION_DATA_USERNAME] === $token->getUsername()) {
            $expires = $oldSessionData[self::SESSION_DATA_EXPIRES];
            $stored = $oldSessionData[self::SESSION_DATA_STARTED] ?? ($expires - $lifetime);
            if ($stored + $refreshAfter < $curTime) {
                $expires = $curTime + $lifetime;
                $logger->debug('Remember-me authenticator: refreshing token "' . $token->getUsername() . '" for another ' . $lifetime . ' seconds - till ' . date('Y-m-d H:i:s', $expires) . '!');
            }
        } else {
            // If the lifetime is 0, we should not store any information at all!
            if ($lifetime === 0) {
                $logger->debug('Remember-me authenticator: will not remember "' . $token->getUsername() . '" because authenticator "' . ($provider ? get_class($provider) : '') . '" has a token lifetime of 0!');
                return '';
            }
            
            $expires = $curTime + $lifetime;
            $logger->debug('Remember-me authenticator: remembering "' . $token->getUsername() . '" for ' . $lifetime . ' seconds - till ' . date('Y-m-d H:i:s', $expires) . '!');
        }
        $user = $this->getWorkbench()->getSecurity()->getUser($token);
        $data[self::SESSION_DATA_USERNAME] = $user->getUsername();
        $data[self::SESSION_DATA_STARTED] = $curTime;
        $data[self::SESSION_DATA_EXPIRES] = $expires;
        $data[self::SESSION_DATA_REFRESH_INTERVAL] = $refreshAfter;
        $data[self::SESSION_DATA_HASH] = $this->generateSessionDataHash($user, $data);
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
    protected function generateSessionDataHash(UserInterface $user, array $sessionData) : string
    {
        $username = $user->getUsername();
        $password = $user->getPassword() ?? '';
        $expires = $sessionData[self::SESSION_DATA_EXPIRES] ?? 0;
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
     * @param AuthenticationTokenInterface $token
     * @param AuthenticationProviderInterface $provider
     * @return int
     */
    protected function getTokenLifetimeDefault(AuthenticationTokenInterface $token, AuthenticationProviderInterface $provider) : int
    {
        $lifetime = ($provider instanceof AuthenticatorInterface) ? null : $provider->getTokenLifetime($token);
        return $lifetime ?? $this->getTokenLifetime($token);
    }
    
    protected function getTokenRefreshIntervalDefault(AuthenticationProviderInterface $provider) : int
    {
        $extendAfter = ($provider instanceof AuthenticatorInterface) ? null : $provider->getTokenRefreshInterval();
        return $extendAfter ?? $this->getTokenRefreshInterval();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getTokenLifetime()
     */
    public function getTokenLifetime(AuthenticationTokenInterface $token) : ?int
    {
        return parent::getTokenLifetime($token) ?? 60*60*24*7;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getTokenRefreshInterval()
     */
    public function getTokenRefreshInterval() : ?int
    {
        return parent::getTokenRefreshInterval() ?? 0;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::createLoginWidget()
     */
    public function createLoginWidget(iContainOtherWidgets $container) : iContainOtherWidgets
    {
        return $container;
    }
}