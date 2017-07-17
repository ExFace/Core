<?php
namespace exface\Core\CommonLogic;

use exface\Core\CommonLogic\Contexts\Scopes\WindowContextScope;
use exface\Core\CommonLogic\Contexts\Scopes\SessionContextScope;
use exface\Core\CommonLogic\Contexts\Scopes\AbstractContextScope;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Contexts\Scopes\ApplicationContextScope;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\Contexts\Scopes\UserContextScope;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use exface\Core\CommonLogic\Contexts\Scopes\RequestContextScope;
use exface\Core\Exceptions\Contexts\ContextScopeNotFoundError;

class ContextManager implements ContextManagerInterface
{

    private $exface = NULL;

    private $window_scope = NULL;

    private $session_scope = NULL;

    private $application_scope = NULL;

    private $user_scope = NULL;

    private $request_scope = null;

    public function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\WindowContextScope
     */
    public function getScopeWindow()
    {
        if (is_null($this->window_scope)){
            $this->window_scope = new WindowContextScope($this->exface);
        }
        return $this->window_scope;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\SessionContextScope
     */
    public function getScopeSession()
    {
        if (is_null($this->session_scope)){
            $this->session_scope = new SessionContextScope($this->exface);
        }
        return $this->session_scope;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\ApplicationContextScope
     */
    public function getScopeApplication()
    {
        if (is_null($this->application_scope)){
            $this->application_scope = new ApplicationContextScope($this->exface);
        }
        return $this->application_scope;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\UserContextScope
     */
    public function getScopeUser()
    {
        if (is_null($this->user_scope)){
            $this->user_scope = new UserContextScope($this->exface);
        }
        return $this->user_scope;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\RequestContextScope
     */
    public function getScopeRequest()
    {
        if (is_null($this->request_scope)){
            $this->request_scope = new RequestContextScope($this->exface);
        }
        return $this->request_scope;
    }

    /**
     * Return an array of all existing context scopes.
     * Usefull to get a context from all scopes
     *
     * @return AbstractContextScope[]
     */
    public function getScopes()
    {
        return array(
            $this->getScopeWindow(),
            $this->getScopeSession(),
            $this->getScopeApplication(),
            $this->getScopeUser(),
            $this->getScopeRequest()
        );
    }

    /**
     * Returns an array of filter conditions from all scopes.
     * If a meta object id is given, only conditions applicable to that object are returned.
     *
     * @param \exface\Core\CommonLogic\Model\Object $meta_object            
     * @return Condition[]
     */
    public function getFilterConditionsFromAllContexts(Object $meta_object)
    {
        $contexts = array();
        foreach ($this->getScopes() as $scope) {
            $contexts = array_merge($contexts, $scope->getFilterContext()->getConditions($meta_object));
        }
        return $contexts;
    }

    /**
     * Saves all contexts in all scopes
     *
     * @return \exface\Core\Context
     */
    public function saveContexts()
    {
        foreach ($this->getScopes() as $scope) {
            $scope->saveContexts();
        }
        return $this;
    }

    /**
     * Returns the context scope specified by the given name (e.g.
     * window, application, etc)
     *
     * @param string $scope_name            
     * @throws ContextScopeNotFoundError if no context scope is found for the given name
     * @return AbstractContextScope
     */
    public function getScope($scope_name)
    {
        if (!$scope_name){
            throw new ContextScopeNotFoundError('Empty context scope name requested!', '6T5E14B');
        }
        
        $getter_method = 'getScope' . ucfirst($scope_name);
        if (! method_exists($this, $getter_method)) {
            $getter_method = 'get_scope_' . $scope_name;
            if (! method_exists($this, $getter_method)) {
                throw new ContextScopeNotFoundError('Context scope "' . $scope_name . '" not found!', '6T5E14B');
            }
        }
        return call_user_func(get_class($this) . '::' . $getter_method);
    }
}
?>