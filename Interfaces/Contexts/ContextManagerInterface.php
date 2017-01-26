<?php namespace exface\Core\Interfaces\Contexts;

use exface\Core\Exceptions\Contexts\ContextScopeNotFoundError;

interface ContextManagerInterface {
	
	const CONTEXT_SCOPE_USER = 'User';
	const CONTEXT_SCOPE_SESSION = 'Session';
	const CONTEXT_SCOPE_WINDOW = 'Window';
	const CONTEXT_SCOPE_REQUEST = 'Request';
	const CONTEXT_SCOPE_APPLICATION = 'Application';
	
	/**
	 * Return an array of all existing context scopes. Usefull to get a context from all scopes
	 * @return ContextScopeInterface[]
	 */
	public function get_sopes();
	
	/**
	 * Saves all contexts in all scopes
	 * @return ContextManagerInterface
	 */
	public function save_contexts();
	
	/**
	 * Returns the context scope specified by the given name (e.g. window, application, etc)
	 * @param string $scope_name
	 * @throws ContextScopeNotFoundError if no context scope is found for the given name
	 * @return ContextScopeInterface
	 */
	public function get_scope($scope_name);
	
	/**
	 *
	 * @return \exface\Core\Contexts\Scopes\WindowContextScope
	 */
	public function get_scope_window();
	
	/**
	 *
	 * @return \exface\Core\Contexts\Scopes\SessionContextScope
	 */
	public function get_scope_session();
	
	/**
	 *
	 * @return \exface\Core\Contexts\Scopes\ApplicationContextScope
	 */
	public function get_scope_application();
	
	/**
	 *
	 * @return \exface\Core\Contexts\Scopes\UserContextScope
	 */
	public function get_scope_user();
	
	/**
	 *
	 * @return \exface\Core\Contexts\Scopes\RequestContextScope
	 */
	public function get_scope_request();
	
}
?>