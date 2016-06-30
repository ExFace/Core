<?php namespace exface\Core\Interfaces\Contexts;

use exface\Core\Interfaces\ExfaceClassInterface;

interface ContextScopeInterface extends ExfaceClassInterface {
	
	/**
	 * Returns an array with all contexts available in this scope.
	 * @return ContextInterface[]
	 */
	public function get_all_contexts();
	
	/**
	 * Returns the context matching the given alias (like "action", "filter", "test", etc.). If the context
	 * is not initialized yet, it will be initialized now and saved contexts will be loaded.
	 * @param string $alias
	 * @return ContextInterface
	 */
	public function get_context($alias);

	/**
	 * Saves data of all contexts in the current scope to the scopes storage
	 * @return ContextScopeInterface
	 */
	public function save_contexts();
	
	/**
	 * Returns a unique identifier of the context scope: e.g. the session id for window or session context, the user id
	 * for user context, the app alias for app contexts, etc. This id is mainly used as a key for storing information from
	 * the context (see session scope example).
	 * @return string
	 */
	public function get_scope_id();
	
	/**
	 * Returns a human readable name for this context scope
	 * @return string
	 */
	public function get_name();
	
}
?>