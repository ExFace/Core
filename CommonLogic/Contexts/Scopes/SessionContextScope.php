<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\Contexts\ContextNotFoundError;
use exface\Core\Facades\ConsoleFacade;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;
use exface\Core\Interfaces\ConfigurationInterface;

/**
 * The session context scope represents the PHP session (on server side).
 * 
 * Contexts in this scope live as long as the PHP session does and are accessible from 
 * all windows of the browser instance, that started the session.
 * 
 * Technically this scope opens and reads the session when being created, closes it immediately
 * to avoid blocking and re-opens it right before storing data at the very end of the request
 * (typically on Workbench::__destroy()). This does not work well with default cookie-based
 * sessions, so there is a workaround for session handling - see sessionOpen() for details!
 *
 * @author Andrej Kabachnik
 *        
 */
class SessionContextScope extends AbstractContextScope
{
    const KEY_USERNAME = 'username';
    
    const KEY_USERDATA = 'user';
    
    const KEY_LOCALE = 'locale';

    private $session_id = null;

    private $session_locale = null;
    
    private $session_user_data = null;
    
    private $force_update_session_data = false;
    
    private $session_vars_set = [];
    
    private $session_vars_unset = [];
    
    private $installation_name = null;
    
    private $session_disabled = false;
    
    private $session_failed = false;
    
    private $useCookieWorkaround = true;
    
    private $cookieName = null;
    
    public function __construct(Workbench $exface)
    {
        $this->installation_name = $exface->getInstallationName();
        $this->cookieName = md5($this->installation_name);
        parent::__construct($exface);
        
        $this->sessionOpen();
        
        if ($locale = $this->getSessionData(self::KEY_LOCALE)) {
            $this->setSessionLocale($locale);
        }
        
        if ($userdata = $this->getSessionData(self::KEY_USERDATA)) {
            $this->session_user_data = $userdata;
        }
        
        // It is important to save the session once we have read the data, because otherwise it will block concurrent ajax-requests
        $this->sessionClose();
    }

    /**
     * Since the session context ist stored in the $_SESSION, init() makes sure, the session is available and tries to
     * instantiate all contexts saved there.
     * Thus, the session contexts are always loaded on startup, not only once they are
     * actually used. This should be OK, since window contexts will probably be used in every single request.
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::init()
     */
    public function init()
    {
        if ($this->getSavedContexts() instanceof UxonObject) {
            foreach ($this->getSavedContexts()->getPropertyNames() as $alias) {
                try {
                    $this->getContext($alias);
                } catch (ContextNotFoundError|AuthorizationExceptionInterface $error) {
                    $this->removeContext($alias);
                }
            }
        }
        
        return parent::init();
    }

    /**
     * Since the session context ist stored in the $_SESSION, loading contexts simply fetches the contents
     * of the contexts array in the $_SESSION variable and tries to parse it as a UXON object.
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::loadContextData()
     */
    public function loadContextData(ContextInterface $context)
    {
        if ($this->getSavedContexts($context->getAliasWithNamespace())) {
            $context->importUxonObject($this->getSavedContexts($context->getAliasWithNamespace()));
        }
        return $this;
    }

    /**
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::getSavedContexts()
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function getSavedContexts($context_alias = null)
    {
        if ($context_alias) {
            $obj = $this->getSessionContextData()[$context_alias];
        } else {
            $obj = $this->getSessionContextData();
        }
        
        return UxonObject::fromAnything($obj);
    }

    /**
     * The session scope saves all it's contexts as UXON objects in the $_SESSION
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::saveContexts()
     */
    public function saveContexts()
    {
        // Do not do anything if no context were loaded (in this case, nothing could change!)
        // In particular, this prevens unneeded session operatinos, which may have negative
        // performance impact and can even produce PHP warnings if headers were sent already
        // (e.g. by the CMS).
        if (empty($this->getContextsLoaded()) === true 
            && empty($this->session_vars_set)
            && empty($this->session_vars_unset)
            && $this->force_update_session_data === false) {
            return $this;
        }
        
        try {
            $this->sessionOpen();
        } catch (\ErrorException $e) {
            if ($e->getSeverity() === E_WARNING) {
                $this->getWorkbench()->getLogger()->logException($e);
                return $this;
            } else {
                return $this;
            }
        }
        
        // Save contexts
        foreach ($this->getContextsLoaded() as $context) {
            $uxon = $context->exportUxonObject();
            if (! is_null($uxon) && ! $uxon->isEmpty()) {
                // Save the context in the session in JSON-Representation, because saving it directly as a UxonObject
                // causes errors when reading the session: all used classes must be declared (included) before the
                // session is initialized. So as long as we are using the CMS session here, we can only store built-in
                // types. If ExFace will create own sessions, this can be changed!
                $this->setSessionContextData($context->getAliasWithNamespace(), $uxon->toJson());
            } else {
                $this->removeContext($context->getAliasWithNamespace());
            }
        }
        
        // Save variables
        foreach ($this->session_vars_set as $var => $val) {
            $this->setSessionData($var, $val);
        }
        foreach (array_keys($this->session_vars_unset) as $var) {
            $this->removeSessionData($var);
        }
        
        // Save other session data
        $this->setSessionData(self::KEY_LOCALE, $this->session_locale);
        $this->setSessionData(self::KEY_USERDATA, $this->session_user_data);
        
        // It is important to save the session once we have read the data, because otherwise it will block concurrent ajax-requests
        $this->sessionClose();
        
        return $this;
    }
        
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::removeContext()
     */
    public function removeContext($alias)
    {
        unset($_SESSION['exface'][$this->installation_name]['contexts'][$alias]);
        return parent::removeContext($alias);
    }

    /**
     * The id of the session scope it the session id.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::getScopeId()
     */
    public function getScopeId()
    {
        return $this->getSessionId();
    }

    /**
     * Sets the session id assotiated with this scope.
     * This is usefull if there are multiple php sessions and only
     * one of them should be used in the session scope.
     *
     * @param string $string            
     * @return \exface\Core\CommonLogic\Contexts\Scopes\SessionContextScope
     */
    protected function setSessionId($string)
    {
        $this->session_id = $string;
        return $this;
    }

    /**
     * Returns the id of the session, that is assotiated with this context scope
     *
     * @return string|NULL
     */
    protected function getSessionId()
    {
        return $this->session_id;
    }

    /**
     * Opens the curernt session for writing; Creates a new session, if there is no session yet.
     * 
     * NOTE: since context data is stored in the session itself and that context data is saved at the
     * very end of the request, the session context scope requires the session to remain writable even 
     * after HTTP headers being sent (which happens somewhere unpredictable along the way). Keeping
     * the session open all the time is not an option as the default file-based sessions would block
     * concurrent AJAX-requests.
     * 
     * If the session is created here and `isCookieHandlingEnabled()` is not disabled explicitly, 
     * this method will handle session cookies itself to avoid "headers already sent" warnings and the
     * session being unable to save any data at the end. Should custom sessions be used or should
     * the workaround cause unwanted side-effects, the whole thing can be disabled by making
     * `isCookieHandlingEnabled()` return false.
     *
     * @return SessionContextScope
     */
    protected function sessionOpen()
    {
        $log = '';
        if (! $this->sessionIsPossible()) {
            $log .= 'Session disabled or not possible' . PHP_EOL;
            return $this;
        }
        
        $cookieName = $this->sessionGetName();
        session_name($cookieName);
        $sessionId = $this->getSessionId();
        $log .= 'Cached id is "' . $sessionId . '"' . PHP_EOL;
        $sessionStartedPreviously = ($sessionId !== null);
        $started = null;
        
        if ($sessionStartedPreviously === false) {
            $cookieHandling = $this->isCookieHandlingEnabled();
            $log .= 'Cookie handler is ' . ($cookieHandling ? 'ON' : 'OFF') . PHP_EOL;
            
            // Custom cookie handling to avoid headers-already-sent-issues. See method comments
            // above!
            if ($cookieHandling === true) {
                // Don't use built-in cookies because they are sent every time `session_start()` is called.
                // This causes problems however, because HTTP headers actually are supposed to be sent with
                // the actual response.
                ini_set('session.use_cookies', false);
                // If session.use_only_cookies=Off, the session module will use the session ID 
                // values set by GET/POST/URL provided the session ID cookie is uninitialized.
                ini_set('session.use_only_cookies', true);
                // Use of a transparent session ID management is not prohibited. Developers may employ it when 
                // it is required. However, disabling transparent session ID management improves the general 
                // session ID security by eliminating the possibility of a session ID injection and/or leak.
                ini_set('session.use_trans_sid', false);
                // This prevents the session module to use an uninitialized session ID. Put differently, 
                // the session module only accepts valid session IDs generated by the session module. 
                // It rejects any session ID supplied by users.
                // FIXME strict_mode seems to cause "Cannot get OAuth2 session: no session was started!" errors
                // ini_set('session.use_strict_mode', true);
                // Ensure HTTP content are uncached for authenticated sessions. Allow caching only when the content 
                // is not private. Otherwise, content may be exposed. "private" may be employed if HTTP content does 
                // not include security sensitive data. Note that "private" may transmit private data cached by shared 
                // clients. "public" must only be used when HTTP content does not contain any private data at all.
                // Setting the cache limiter to '' will turn off automatic sending of cache headers entirely.
                ini_set('session.cache_limiter', null);
                
                if (array_key_exists($cookieName, $_COOKIE)) {
                    $sessionId = $_COOKIE[$cookieName];
                    session_id($sessionId);
                    $this->setSessionId($sessionId);
                    $log .= 'Cookie found with session id "' . $sessionId . '"' . PHP_EOL;
                } else {
                    $started = session_start();
                    $log .= 'Session start to generate new cookie ' . ($started ? 'OK' : 'FAILED') . PHP_EOL;
                    if ($started !== false) {
                        $sessionId = session_id();
                        $this->setSessionId($sessionId);
                        $this->sessionSetCookie($sessionId);
                        session_write_close();
                    }
                }
            }
        } 
        
        if ($started === null) {
            try {
                $started = session_start();
                $log .= 'Session start ' . ($started ? 'OK' : 'FAILED') . PHP_EOL;
            } catch (\Throwable $e) {
                if (! $this->sessionIsOpen()) {
                    $this->session_failed = true;
                    throw new RuntimeException('Opening the session for the session context scope failed: ' . $e->getMessage(), null, $e);
                }
            }
        }
        // Throw an error if the session could not be started. 
        if ($started === false) {
            $this->session_failed = true;
            throw new RuntimeException('Opening the session for the session context scope failed: unknown error!');
        } else {
            $this->setSessionId(session_id());
        }
            
        return $this;
    }
    
    protected function sessionSetCookie(string $sessionId) : bool
    {
        $config = $this->getWorkbench()->getConfig();
        $cookieSent = setcookie(
            $this->sessionGetName(), // name
            $sessionId, // value
            (time() + $config->getOption('SECURITY.SESSION_COOKIE_LIFETIME')), // expires
            $config->getOption('SECURITY.SESSION_COOKIE_PATH'), // path
            null, // domain
            $config->getOption('SECURITY.FORCE_HTTPS'), // secure
            true // httponly
        );
        return $cookieSent;
    }
    
    protected function sessionDestroy() : SessionContextScope
    {
        session_start();
        $success = session_destroy();
        $this->session_id = null;
        return $this;
    }
    
    protected function sessionRegenerateId(bool $destroyPreviousSession = true) : ?string
    {
        if ($this->sessionIsPossible() === false) {
            return null;
        }
        session_start();
        $success = session_regenerate_id($destroyPreviousSession);
        $newId = session_id();
        session_write_close();
        if ($success === false) {
            $this->getWorkbench()->getLogger()->logException(new RuntimeException('Cannot regenerate session id: "' . $this->session_id . '" -> "' . $newId . '"'));
        } else {
            $this->session_id = $newId;
            if ($this->isCookieHandlingEnabled() === true) {
                $this->sessionSetCookie($newId);
            }
        }
        return $newId;
    }
    
    protected function sessionGetName() : string
    {
        return $this->cookieName;
    }
    
    /**
     * Retruns TRUE if custom session cookie handling is required and FALSE otherwise.
     * 
     * @return bool
     */
    protected function isCookieHandlingEnabled() : bool
    {
        return $this->useCookieWorkaround;
    }

    /**
     * Closes the session, but does not empty the context data.
     * This way, the session is not locked any more and can be used by
     * other threads/processes
     *
     * @return SessionContextScope
     */
    protected function sessionClose()
    {
        if ($this->sessionIsPossible()) {
            if ($this->getSessionId() === null) {
                $this->setSessionId(session_id());
            }
            session_write_close();
        }
        return $this;
    }

    /**
     * Returns TRUE if the current session is open and active and FALSE otherwise
     *
     * @return boolean
     */
    protected function sessionIsOpen()
    {
        if (php_sapi_name() !== 'cli') {
            if (version_compare(phpversion(), '5.4.0', '>=')) {
                return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
            } else {
                return session_id() === '' ? FALSE : TRUE;
            }
        }
        return FALSE;
    }
    
    protected function sessionIsPossible() : bool
    {
        return $this->session_failed === false && $this->session_disabled === false && ! ConsoleFacade::isPhpScriptRunInCli();
    }

    /**
     * Returns the raw array of context data from the current session
     *
     * @return array
     */
    protected function getSessionContextData()
    {
        return $_SESSION['exface'][$this->installation_name]['contexts'];
    }

    /**
     * Writes data to the session
     *
     * @param string $key            
     * @param string $value            
     * @return \exface\Core\CommonLogic\Contexts\Scopes\SessionContextScope
     */
    protected function setSessionContextData($key, $value)
    {
        $_SESSION['exface'][$this->installation_name]['contexts'][$key] = $value;
        return $this;
    }
    
    /**
     * 
     * @param string $key
     * @return mixed
     */
    protected function getSessionData(string $key)
    {
        return $_SESSION['exface'][$this->installation_name][$key];
    }
    
    /**
     * Writeds data to $_SESSION - only persistant if the session is open!!!
     * 
     * NOTE: the session is closed after the context is initialized, so all changes
     * get reverted unless explicitly re-applied in `save_contexts()`. Changing
     * the $_SESSION here is only temporary if the session is closed at the time
     * of change!
     * 
     * @param string $key
     * @param string|array $data
     * @return SessionContextScope
     */
    protected function setSessionData(string $key, $data) : SessionContextScope
    {
        $_SESSION['exface'][$this->installation_name][$key] = $data;
        return $this;
    }
    
    /**
     * Unsets data in $_SESSION - only persistant if the session is open!!!
     * 
     * NOTE: the session is closed after the context is initialized, so all changes
     * get reverted unless explicitly re-applied in `save_contexts()`. Changing
     * the $_SESSION here is only temporary if the session is closed at the time
     * of change!
     * 
     * @param string $key
     * @return SessionContextScope
     */
    protected function removeSessionData(string $key) : SessionContextScope
    {
        unset($_SESSION['exface'][$this->installation_name][$key]);
        return $this;
    }
    
    /**
     * 
     * @return SessionContextScope
     */
    public function clearSessionData() : SessionContextScope
    {
        unset($_SESSION['exface'][$this->installation_name]);
        if (empty($_SESSION['exface'])) {
            unset($_SESSION['exface']);
            $this->sessionDestroy();
        }
        
        $this->session_locale = null;
        $this->session_user_data = null;
        $this->force_update_session_data = true;
        
        foreach ($this->getContextsLoaded() as $context) {
            $this->reloadContext($context);
        }
        return $this;
    }

    /**
     * Returns the locale used in the current session.
     * If no locale was set for the session explicitly, the locale from
     * the user context scope is returned.
     *
     * @return string
     */
    public function getSessionLocale()
    {
        if ($this->session_locale === null) {
            try {
                $this->session_locale = $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getLocale();
            } catch (\Throwable $e){
                $this->session_locale = $this->getWorkbench()->getConfig()->getOption('SERVER.DEFAULT_LOCALE');
            }
        }
        return $this->session_locale;
    }

    /**
     * Sets the locale to be used in the current session
     *
     * @param string $value            
     * @return SessionContextScope
     */
    protected function setSessionLocale(string $value) : SessionContextScope
    {
        $this->session_locale = $value;
        return $this;
    }
    
    /**
     * Set the session user data. 
     * 
     * @param string|NULL $data
     * @return SessionContextScope
     */
    public function setSessionUserData(?string $data) : SessionContextScope
    {
        if ($data !== $this->session_user_data) {
            $this->clearSessionData();
            $this->sessionRegenerateId();
            $this->session_user_data = $data;
        }
        return $this;
    }
    
    /**
     * Return the session user data.
     * 
     * @return string|NULL
     */
    public function getSessionUserData() : ?string
    {
        return $this->session_user_data;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::setVariable()
     */
    public function setVariable(string $name, $value, string $namespace = null) : ContextScopeInterface
    {
        $var = '_' . ($namespace !== null ? $namespace . '_' : '') . $name;
        $this->setSessionData($var, $value);
        $this->session_vars_set[$var] = $value;
        // If this variable was unset previously, remove it from the unset-list
        unset($this->session_vars_unset[$var]);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::unsetVariable()
     */
    public function unsetVariable(string $name, string $namespace = null) : ContextScopeInterface
    {
        $var = '_' . ($namespace !== null ? $namespace . '_' : '') . $name;
        $this->session_vars_unset[$var] = $this->getSessionData($var);
        // If this variable was set previously, remove it from the set-list
        unset($this->session_vars_set[$var]);
        $this->removeSessionData($var);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::getVariable()
     */
    public function getVariable(string $name, string $namespace = null)
    {
        return $this->getSessionData('_' . ($namespace !== null ? $namespace . '_' : '') . $name);
    }
    
    public function setSessionDisabled(bool $trueOrFalse) : SessionContextScope
    {
        $this->session_disabled = $trueOrFalse;
        return $this;
    }
}