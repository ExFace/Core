<?php namespace exface\Core\Interfaces\Contexts;

use exface\Core\Exceptions\ContextError;

interface ContextManagerInterface {
	
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
	 * @throws ContextError if no context scope is found for the given name
	 * @return ContextScopeInterface
	 */
	public function get_scope($scope_name);
}
?>