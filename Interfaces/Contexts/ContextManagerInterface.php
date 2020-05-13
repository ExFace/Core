<?php
namespace exface\Core\Interfaces\Contexts;

use exface\Core\Exceptions\Contexts\ContextScopeNotFoundError;
use exface\Core\CommonLogic\Contexts\Scopes\WindowContextScope;
use exface\Core\CommonLogic\Contexts\Scopes\SessionContextScope;
use exface\Core\CommonLogic\Contexts\Scopes\ApplicationContextScope;
use exface\Core\CommonLogic\Contexts\Scopes\UserContextScope;
use exface\Core\CommonLogic\Contexts\Scopes\RequestContextScope;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface ContextManagerInterface
{

    const CONTEXT_SCOPE_USER = 'User';

    const CONTEXT_SCOPE_SESSION = 'Session';

    const CONTEXT_SCOPE_WINDOW = 'Window';

    const CONTEXT_SCOPE_REQUEST = 'Request';

    const CONTEXT_SCOPE_APPLICATION = 'Application';

    /**
     * Return an array of all existing context scopes.
     * Usefull to get a context from all scopes
     *
     * @return ContextScopeInterface[]
     */
    public function getScopes() : array;

    /**
     * Saves all contexts in all scopes
     *
     * @return ContextManagerInterface
     */
    public function saveContexts() : ContextManagerInterface;

    /**
     * Returns the context scope specified by the given name (e.g.
     * window, application, etc)
     *
     * @param string $scope_name            
     * @throws ContextScopeNotFoundError if no context scope is found for the given name
     * @return ContextScopeInterface
     */
    public function getScope($scope_name) : ContextScopeInterface;

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\WindowContextScope
     */
    public function getScopeWindow() : WindowContextScope;

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\SessionContextScope
     */
    public function getScopeSession() : SessionContextScope;

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\ApplicationContextScope
     */
    public function getScopeApplication() : ApplicationContextScope;

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\UserContextScope
     */
    public function getScopeUser() : UserContextScope;

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\RequestContextScope
     */
    public function getScopeRequest() : RequestContextScope;
}