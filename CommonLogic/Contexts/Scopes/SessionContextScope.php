<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Exceptions\Contexts\ContextNotFoundError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\UserException;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;

/**
 * The session context scope represents the PHP session (on server side).
 * Contexts in this scope live as long as
 * the session does and are accessible from all windows of the browser instance, that started the session.
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
    
    private $session_user = null;
    
    private $session_user_data = null;
    
    private $force_update_session_data = false;
    
    private $sessionData = [];
    
    public function __construct(Workbench $exface)
    {
        parent::__construct($exface);
        
        $this->sessionOpen();
        
        $this->sessionData = $_SESSION['exface'][$this->getWorkbench()->getInstallationFolderName()];
        
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
            foreach ($this->getSavedContexts() as $alias => $uxon) {
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
        if (empty($this->getContextsLoaded()) === true && $this->force_update_session_data === false) {
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
        
        // Save other session data
        $this->setSessionData(self::KEY_LOCALE, $this->session_locale);
        //$this->setSessionData(self::KEY_USERNAME, $this->session_user);
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
        unset($_SESSION['exface'][$this->getWorkbench()->getInstallationFolderName()]['contexts'][$alias]);
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
     * @return string
     */
    protected function getSessionId()
    {
        return $this->session_id;
    }

    /**
     * Opens the curernt session for writing.
     * Creates a new session, if there is no session yet
     *
     * @return SessionContextScope
     */
    protected function sessionOpen()
    {
        if (! $this->sessionIsOpen()) {
            // If there is a session id saved in the context, this session was already loaded into it, so the next time
            // we need to open exactly the same session!
            if ($this->getSessionId()) {
                session_id($this->getSessionId());
            }
            
            // It is important to wrap session_start() in a try-catch-block because it can produce warnings on certain
            // occasions (e.g. if the session uses cookies and the headers were already sent at this point), that may be
            // converted to exceptions if a corresponding error handler is being used. Exceptions would prevent the
            // rest of the code from being executed and, thus, the purpose of opening the session will not be fulfilled.
            // To prevent this, we simply catch any exception and check if the session is really open afterwards - if not,
            // a meaningfull exception is thrown.
            try {
                @session_start();
            } catch (\Throwable $e) {
                if (! $this->sessionIsOpen()) {
                    throw new RuntimeException('Opening the session for the session context scope failed: ' . $e->getMessage(), null, $e);
                }
            }
        } else {
            $this->setSessionId(session_id());
        }
        return $this;
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
        if (! $this->getSessionId()) {
            $this->setSessionId(session_id());
        }
        session_write_close();
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

    /**
     * Returns the raw array of context data from the current session
     *
     * @return array
     */
    protected function getSessionContextData()
    {
        return $_SESSION['exface'][$this->getWorkbench()->getInstallationFolderName()]['contexts'];
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
        $_SESSION['exface'][$this->getWorkbench()->getInstallationFolderName()]['contexts'][$key] = $value;
        return $this;
    }
    
    /**
     * 
     * @param string $key
     * @return mixed
     */
    protected function getSessionData(string $key)
    {
        return $_SESSION['exface'][$this->getWorkbench()->getInstallationFolderName()][$key];
    }
    
    /**
     * 
     * @param string $key
     * @param string|array $data
     * @return SessionContextScope
     */
    protected function setSessionData(string $key, $data) : SessionContextScope
    {
        $_SESSION['exface'][$this->getWorkbench()->getInstallationFolderName()][$key] = $data;
        return $this;
    }
    
    protected function clearSessionData() : SessionContextScope
    {
        unset($_SESSION['exface'][$this->getWorkbench()->getInstallationFolderName()]);
        $this->session_locale = null;
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
                $this->session_locale = $this->getContextManager()->getScopeUser()->getUserCurrent()->getLocale();
            } catch (UserException $e){
                $this->session_locale = $this->getWorkbench()->getConfig()->getOption('LOCALE.DEFAULT');
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
    
    public function setSessionAuthToken(AuthenticationTokenInterface $token) : SessionContextScope
    {
        $userChanged = $this->session_user !== $token->getUsername();
        if ($token->getUsername() === null) {
            $this->unsetSessionUsername();
        } else {
            $this->setSessionUsername($token->getUsername());
        }
        if ($userChanged === true) {
            $this->clearSessionData();
            foreach ($this->getContextsLoaded() as $context) {
                $this->reloadContext($context);
            }
        }
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
            $this->session_user_data = $data;
            $this->force_update_session_data = true;
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
     * @return string|NULL
     */
    public function getSessionUsername() : ?string
    {
        return $this->session_user;
    }
    
    /**
     * 
     * @param string $value
     * @return SessionContextScope
     */
    protected function setSessionUsername(string $value) : SessionContextScope
    {
        if ($this->session_user !== $value) {
            $this->session_user = $value;
            $this->force_update_session_data = true;
        }
        return $this;
    }
    
    /**
     * 
     * @return SessionContextScope
     */
    protected function unsetSessionUsername() : SessionContextScope
    {
        if ($this->session_user !== null) {
            $this->session_user = null;
            $this->force_update_session_data = true;
        }
        return $this;
    }
}