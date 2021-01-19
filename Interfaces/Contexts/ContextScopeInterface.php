<?php
namespace exface\Core\Interfaces\Contexts;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Selectors\ContextSelectorInterface;

interface ContextScopeInterface extends WorkbenchDependantInterface
{

    /**
     * Returns an array with all contexts already loaded in this scope.
     *
     * @return ContextInterface[]
     */
    public function getContextsLoaded();

    /**
     * Returns the context matching the given alias (like "action", "filter", "test", etc.).
     * 
     * If the context is not loaded yet, it will be initialized now and saved 
     * contexts will be loaded.
     *
     * @param string|ContextSelectorInterface $aliasOrSelector            
     * @return ContextInterface
     */
    public function getContext($aliasOrSelector) : ContextInterface;
    
    /**
     * Refreshes the data within the context with the latest information from the scope storage.
     * 
     * NOTE: This will overwrite any changes not saved via saveContexts()!
     * 
     * @param ContextInterface $context
     * @return void
     */
    public function reloadContext(ContextInterface $context);
    
    /**
     * Removes the context matching the given alias from the scope
     * 
     * @param string $alias
     * @return ContextScopeInterface
     */
    public function removeContext($alias);

    /**
     * Saves data of all contexts in the current scope to the scopes storage
     *
     * @return ContextScopeInterface
     */
    public function saveContexts();

    /**
     * Returns the ContextManager, which this context belongs to
     *
     * @return ContextManagerInterface
     */
    public function getContextManager();

    /**
     * Returns a unique identifier of the context scope: e.g.
     * the session id for window or session context, the user id
     * for user context, the app alias for app contexts, etc. This id is mainly used as a key for storing information from
     * the context (see session scope example).
     *
     * @return string
     */
    public function getScopeId();

    /**
     * Returns a human readable name for this context scope: e.g. "Window" for the window context scope
     *
     * @return string
     */
    public function getName();
    
    /**
     * Stores an arbitrary name-value pair in the context.
     * 
     * @param string $name
     * @param mixed $value
     * @param string $namespace
     * @return ContextScopeInterface
     */
    public function setVariable(string $name, $value, string $namespace = null) : ContextScopeInterface;
    
    /**
     * Removes a previously stored name-value pair from the context.
     * 
     * @param string $name
     * @param string $namespace
     * @return ContextScopeInterface
     */
    public function unsetVariable(string $name, string $namespace = null) : ContextScopeInterface;
    
    /**
     * Retrieves the value of a previously stored name-value pair from the context.
     * 
     * @param string $name
     * @param string $namespace
     * @return mixed
     */
    public function getVariable(string $name, string $namespace = null);
}
?>