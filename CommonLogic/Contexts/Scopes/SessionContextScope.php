<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Exceptions\Contexts\ContextNotFoundError;
use exface\Core\Exceptions\RuntimeException;

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

    private $session_id = null;

    private $session_locale = null;

    /**
     * Since the session context ist stored in the $_SESSION, init() makes sure, the session is available and tries to
     * instantiate all contexts saved there.
     * Thus, the session contexts are always loaded on startup, not only once they are
     * actually used. This should be OK, since window contexts will probably be used in every single request.
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::init()
     */
    protected function init()
    {
        $this->sessionOpen();
        if ($this->getSavedContexts() instanceof UxonObject || is_array($this->getSavedContexts())) {
            foreach ($this->getSavedContexts() as $alias => $uxon) {
                try {
                    $this->getContext($alias);
                } catch (ContextNotFoundError $error) {
                    $this->removeContext($alias);
                }
            }
        }
        
        // It is important to save the session once we have read the data, because otherwise it will block concurrent ajax-requests
        $this->sessionClose();
        
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
            $obj = $this->getSessionData()[$context_alias];
        } else {
            $obj = $this->getSessionData();
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
        // var_dump($_SESSION);
        $this->sessionOpen();
        
        foreach ($this->getContextsLoaded() as $context) {
            $uxon = $context->exportUxonObject();
            if (! is_null($uxon) && ! $uxon->isEmpty()) {
                // Save the context in the session in JSON-Representation, because saving it directly as a UxonObject
                // causes errors when reading the session: all used classes must be declared (included) before the
                // session is initialized. So as long as we are using the CMS session here, we can only store built-in
                // types. If ExFace will create own sessions, this can be changed!
                $this->setSessionData($context->getAliasWithNamespace(), $uxon->toJson());
            } else {
                $this->removeContext($context->getAliasWithNamespace());
            }
        }
        
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
        unset($_SESSION['exface']['contexts'][$alias]);
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
                session_start();
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
    protected function getSessionData()
    {
        return $_SESSION['exface']['contexts'];
    }

    /**
     * Writes data to the session
     *
     * @param string $key            
     * @param string $value            
     * @return \exface\Core\CommonLogic\Contexts\Scopes\SessionContextScope
     */
    protected function setSessionData($key, $value)
    {
        $_SESSION['exface']['contexts'][$key] = $value;
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
        if (is_null($this->session_locale)) {
            return $this->getContextManager()->getScopeUser()->getUserLocale();
        }
        return $this->session_locale;
    }

    /**
     * Sets the locale to be used in the current session
     *
     * @param string $value            
     * @return SessionContextScope
     */
    public function setSessionLocale($value)
    {
        $this->session_locale = $value;
        return $this;
    }
}
?>