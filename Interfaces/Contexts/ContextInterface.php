<?php namespace exface\Core\Interfaces\Contexts;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\ExfaceClassInterface;

interface ContextInterface extends iCanBeConvertedToUxon, ExfaceClassInterface {
	
	/**
	 * Returns the scope of this speicific context
	 * @return ContextScopeInterface
	 */
	public function get_scope();
	
	/**
	 * Sets the scope for this specific context
	 * @param AbstractContextScope $context_scope
	 * @return AbstractContext
	 */
	public function set_scope(ContextScopeInterface &$context_scope);
	
	/**
	 * Returns the default scope for this type of context.
	 * @return ContextScopeInterface
	 */
	public function get_default_scope();
	
	/**
	 * Returns the alias (name) of the context - e.g. "Filter" for the FilterContext, etc.
	 * @return string
	 */
	public function get_alias();
}
?>