<?php
namespace exface\Core\CommonLogic\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;

/**
 * The window scope exists in every browser window separately (in contrast to the session scope, which is bound to a single "user login").
 * CAUTION: Keep in mind, that concurrent ajax requests from the same window will use the same window scope, so the request that gets finished last
 * will actually overwrite data from the other requests.
 *
 * TODO Currently the window scope atually utilizes the session scope, so it does not really work correctly. It jus
 * delegates everything to the session scope for now. The idea is to fix this by sending a window specific session id
 * with each request.
 *
 * @author Andrej Kabachnik
 *        
 */
class WindowContextScope extends AbstractContextScope
{

    /**
     * The window scope currently just delegates to the session scope, which actually takes care of saving and loading data
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::saveContexts()
     */
    public function saveContexts()
    {
        // Do nothing untill the windows scope is separated from the session scope
    }

    /**
     * The window scope currently just delegates to the session scope, which actually takes care of saving and loading data
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::loadContextData()
     */
    public function loadContextData(ContextInterface $context)
    {
        $this->getContextManager()->getScopeSession()->loadContextData($context);
    }
    
    /**
     * The window scope currently just delegates to the session scope, which actually takes care of saving and loading data
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::reloadContext()
     */
    public function reloadContext(ContextInterface $context)
    {
        $this->getContextManager()->getScopeSession()->reloadContext($context);
    }

    /**
     * TODO The session id should get somehow bound to a window, since the window context scope only exists in a
     * specific instance of ExFace in contrast to the session context scope, which actually is quite like the php session!
     * For now we just return the session scopr id (session id) here.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::getScopeId()
     */
    public function getScopeId()
    {
        return $this->getContextManager()->getScopeSession()->getScopeId();
    }

    /**
     * Delegate everything to the session scope until there is a proper implementation for the window scope
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::getContext()
     */
    public function getContext($aliasOrSelector) : ContextInterface
    {
        if (! $context = $this->getContextsLoaded()[(string)$aliasOrSelector]){
            // Initialize the context in the session scope just to have it included 
            // in the next getContextsLoaded()
            $context = $this->getContextManager()->getScopeSession()->getContext($aliasOrSelector);
            $context->setScope($this);
        }
        return $context;
    }

    /**
     * Delegate everything to the session scope until there is a proper implementation for the window scope
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope::getContextsLoaded()
     */
    public function getContextsLoaded()
    {
        $contexts = [];
        foreach ($this->getContextManager()->getScopeSession()->getContextsLoaded() as $key => $context){
            $context->setScope($this);
            $contexts[$key] = $context;
        }
        return $contexts;
    }
    
    /**
     *
     * @param string $name
     * @param mixed $value
     * @param string $namespace
     * @return ContextScopeInterface
     */
    public function setVariable(string $name, $value, string $namespace = null) : ContextScopeInterface
    {
        $this->getContextManager()->getScopeSession()->setVariable($name, $value, $namespace);
        return $this;
    }
    
    /**
     *
     * @param string $name
     * @param string $namespace
     * @return ContextScopeInterface
     */
    public function unsetVariable(string $name, string $namespace = null) : ContextScopeInterface
    {
        $this->getContextManager()->getScopeSession()->unsetVariable($name, $namespace);
        return $this;
    }
    
    /**
     *
     * @param string $name
     * @param string $namespace
     * @return mixed
     */
    public function getVariable(string $name, string $namespace = null)
    {
        return $this->getContextManager()->getScopeSession()->getVariable($name, $namespace);
    }
}