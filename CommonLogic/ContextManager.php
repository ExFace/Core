<?php namespace exface\Core\CommonLogic;

use exface\Core\Contexts\Scopes\WindowContextScope;
use exface\Core\Contexts\Scopes\SessionContextScope;
use exface\Core\Contexts\Scopes\AbstractContextScope;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Contexts\Scopes\ApplicationContextScope;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Contexts\Scopes\UserContextScope;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use exface\Core\Contexts\Scopes\RequestContextScope;
use exface\Core\Exceptions\Contexts\ContextScopeNotFoundError;

class ContextManager implements ContextManagerInterface {
	
	private $exface = NULL;
	private $window_scope = NULL;
	private $session_scope = NULL;
	private $application_scope = NULL;
	private $user_scope = NULL;
	private $request_scope = null;
	
	public function __construct(\exface\Core\CommonLogic\Workbench $exface){
		$this->exface = $exface;
		$this->window_scope = new WindowContextScope($this->exface);
		$this->session_scope = new SessionContextScope($this->exface);
		$this->application_scope = new ApplicationContextScope($this->exface);
		$this->user_scope = new UserContextScope($this->exface);
		$this->request_scope = new RequestContextScope($this->exface);
	}
	
	/**
	 * 
	 * @return \exface\Core\Contexts\Scopes\WindowContextScope
	 */
	public function get_scope_window(){
		return $this->window_scope;
	}
	
	/**
	 * 
	 * @return \exface\Core\Contexts\Scopes\SessionContextScope
	 */
	public function get_scope_session(){
		return $this->session_scope;
	}
	
	/**
	 * 
	 * @return \exface\Core\Contexts\Scopes\ApplicationContextScope
	 */
	public function get_scope_application(){
		return $this->application_scope;
	}
	
	/**
	 * 
	 * @return \exface\Core\Contexts\Scopes\UserContextScope
	 */
	public function get_scope_user(){
		return $this->user_scope;
	}
	
	/**
	 * 
	 * @return \exface\Core\Contexts\Scopes\RequestContextScope
	 */
	public function get_scope_request(){
		return $this->request_scope;
	}
	
	/**
	 * Return an array of all existing context scopes. Usefull to get a context from all scopes
	 * @return AbstractContextScope[]
	 */
	public function get_sopes(){
		return array(
			$this->get_scope_window(),
			$this->get_scope_session(),
			$this->get_scope_application(),
			$this->get_scope_user(),
			$this->get_scope_request()
		);
	}
	
	/**
	 * Returns an array of filter conditions from all scopes. If a meta object id is given, only conditions applicable to that object are returned.
	 * @param \exface\Core\CommonLogic\Model\Object $meta_object
	 * @return Condition[]
	 */
	public function get_filter_conditions_from_all_contexts(Object $meta_object){
		$contexts = array();
		foreach ($this->get_sopes() as $scope){
			$contexts = array_merge($contexts, $scope->get_filter_context()->get_conditions_for_object($meta_object));
		}
		return $contexts;
	}
	
	/**
	 * Saves all contexts in all scopes
	 * @return \exface\Core\Context
	 */
	public function save_contexts(){
		foreach ($this->get_sopes() as $scope){
			$scope->save_contexts();
		}
		return $this;
	}
	
	/**
	 * Returns the context scope specified by the given name (e.g. window, application, etc)
	 * @param string $scope_name
	 * @throws ContextScopeNotFoundError if no context scope is found for the given name
	 * @return AbstractContextScope
	 */
	public function get_scope($scope_name){
		$getter_method = 'getScope' . ucfirst($scope_name);
		if (!method_exists($this, $getter_method)){
			$getter_method = 'get_scope_' . $scope_name;
			if (!method_exists($this, $getter_method)){
				throw new ContextScopeNotFoundError('Context scope "' . $scope_name . '" not found!', '6T5E14B');
			}
		}
		return call_user_func(get_class($this) . '::' . $getter_method);
	}
}
?>