<?php
namespace exface\Core\CommonLogic;

use exface\Core\CommonLogic\Contexts\Scopes\WindowContextScope;
use exface\Core\CommonLogic\Contexts\Scopes\SessionContextScope;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Contexts\Scopes\ApplicationContextScope;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Contexts\Scopes\UserContextScope;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use exface\Core\CommonLogic\Contexts\Scopes\RequestContextScope;
use exface\Core\Exceptions\Contexts\ContextScopeNotFoundError;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\CommonLogic\Contexts\Scopes\InstallationContextScope;

/**
 * Default implementation of the ContextManagerInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class ContextManager implements ContextManagerInterface
{
    private $exface = null;

    private $window_scope = null;

    private $session_scope = null;

    private $application_scope = null;

    private $user_scope = null;

    private $request_scope = null;
    
    private $installation_scope = null;

    /**
     * 
     * @param \exface\Core\CommonLogic\Workbench $exface
     */
    public function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextManagerInterface::getScopeWindow()
     */
    public function getScopeWindow() : WindowContextScope
    {
        if ($this->window_scope === null){
            $this->window_scope = new WindowContextScope($this->exface);
            $this->window_scope->init();
        }
        return $this->window_scope;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextManagerInterface::getScopeSession()
     */
    public function getScopeSession() : SessionContextScope
    {
        if ($this->session_scope === null){
            $this->session_scope = new SessionContextScope($this->exface);
            $this->session_scope->init();
        }
        return $this->session_scope;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextManagerInterface::getScopeApplication()
     */
    public function getScopeApplication() : ApplicationContextScope
    {
        if ($this->application_scope === null){
            $this->application_scope = new ApplicationContextScope($this->exface);
            $this->application_scope->init();
        }
        return $this->application_scope;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextManagerInterface::getScopeUser()
     */
    public function getScopeUser() : UserContextScope
    {
        if ($this->user_scope === null){
            $this->user_scope = new UserContextScope($this->exface);
            $this->user_scope->init();
        }
        return $this->user_scope;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextManagerInterface::getScopeInstallation()
     */
    public function getScopeInstallation() : InstallationContextScope
    {
        if ($this->installation_scope === null){
            $this->installation_scope = new InstallationContextScope($this->exface);
            $this->installation_scope->init();
        }
        return $this->installation_scope;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Contexts\Scopes\RequestContextScope
     */
    public function getScopeRequest() : RequestContextScope
    {
        if ($this->request_scope === null){
            $this->request_scope = new RequestContextScope($this->exface);
            $this->request_scope->init();
        }
        return $this->request_scope;
    }

    /**
     * Return an array of all existing context scopes.
     * Usefull to get a context from all scopes
     *
     * @return ContextScopeInterface[]
     */
    public function getScopes() : array
    {
        return array(
            $this->getScopeWindow(),
            $this->getScopeSession(),
            $this->getScopeApplication(),
            $this->getScopeUser(),
            $this->getScopeRequest(),
            $this->getScopeInstallation()
        );
    }

    /**
     * Returns an array of filter conditions from all scopes.
     * If a meta object id is given, only conditions applicable to that object are returned.
     *
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $meta_object            
     * @return Condition[]
     */
    public function getFilterConditionsFromAllContexts(MetaObjectInterface $meta_object)
    {
        $contexts = array();
        foreach ($this->getScopes() as $scope) {
            $contexts = array_merge($contexts, $scope->getFilterContext()->getConditions($meta_object));
        }
        return $contexts;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextManagerInterface::saveContexts()
     */
    public function saveContexts() : ContextManagerInterface
    {
        foreach ($this->getScopes() as $scope) {
            $scope->saveContexts();
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Contexts\ContextManagerInterface::getScope()
     */
    public function getScope($scope_name) : ContextScopeInterface
    {
        if (!$scope_name){
            throw new ContextScopeNotFoundError('Empty context scope name requested!', '6T5E14B');
        }
        
        $getter_method = 'getScope' . ucfirst($scope_name);
        if (! method_exists($this, $getter_method)) {
            throw new ContextScopeNotFoundError('Context scope "' . $scope_name . '" not found!', '6T5E14B');
        }
        return call_user_func(get_class($this) . '::' . $getter_method);
    }
}