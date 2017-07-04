<?php
namespace exface\Core\Interfaces\Contexts;

use exface\Core\Exceptions\Contexts\ContextScopeNotFoundError;

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
    public function getScopes();

    /**
     * Saves all contexts in all scopes
     *
     * @return ContextManagerInterface
     */
    public function saveContexts();

    /**
     * Returns the context scope specified by the given name (e.g.
     * window, application, etc)
     *
     * @param string $scope_name            
     * @throws ContextScopeNotFoundError if no context scope is found for the given name
     * @return ContextScopeInterface
     */
    public function getScope($scope_name);

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\WindowContextScope
     */
    public function getScopeWindow();

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\SessionContextScope
     */
    public function getScopeSession();

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\ApplicationContextScope
     */
    public function getScopeApplication();

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\UserContextScope
     */
    public function getScopeUser();

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\RequestContextScope
     */
    public function getScopeRequest();
}
?>