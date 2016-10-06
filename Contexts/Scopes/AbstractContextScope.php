<?php namespace exface\Core\Contexts\Scopes;

use exface\Core\Exceptions\ContextError;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Contexts\Types\FilterContext;
use exface\Core\Contexts\Types\ActionContext;
use exface\Core\Contexts\Types\AbstractContext;

abstract class AbstractContextScope implements ContextScopeInterface {
	private $active_contexts = array();
	private $exface = NULL;
	private $name = null;
	
	public function __construct(&$exface){
		$this->exface = $exface;
		$this->init();
		$this->name = substr(get_class($this), (strrpos(get_class($this), '\\')+1));
	}
	
	/**
	 * Performs all neccessary logic to get the context scope up and running. This may be connecting to DBs,
	 * reading files, preparing data structures, etc. This method is called right after each context scope is 
	 * created.
	 * @return AbstractContextScope
	 */
	protected function init(){
		return $this;
	}
	
	/**
	 * Returns the filter context of the current scope. Shortcut for calling get_context('filter')
	 * @return FilterContext
	 */
	public function get_filter_context(){
		return $this->get_context('Filter');
	}
	
	/**
	 * Returns the action context of the current scope. Shortcut for calling get_context ('action')
	 * @return ActionContext
	 */
	public function get_action_context(){
		return $this->get_context('Action');
	}
	
	/**
	 * Returns an array with all contexts available in this scope.
	 * @return AbstractContext[]
	 */
	public function get_all_contexts(){
		return $this->active_contexts;
	}
	
	/**
	 * Returns the context matching the given alias (like "action", "filter", "test", etc.). If the context
	 * is not initialized yet, it will be initialized now and saved contexts will be loaded.
	 * @param string $alias
	 * @return AbstractContext
	 */
	public function get_context($alias){
		// If no context matching the alias exists, try to create one
		if (!$this->active_contexts[$alias]){
			$context_class = '\\exface\\Core\\Contexts\\Types\\' . ucfirst(strtolower($alias)) . 'Context';
			if (class_exists($context_class)){
				$context = new $context_class($this->exface);
				$context->set_scope($this);
				$this->load_context_data($context);
				$this->active_contexts[$alias] = $context;
			} else {
				throw new ContextError('Cannot create context "' . $alias . '": class not found!');
			}
		}
		return $this->active_contexts[$alias];
	}
	
	/**
	 * Loads data saved in the current context scope into the given context object
	 * @return AbstractContextScope
	 */
	abstract public function load_context_data(ContextInterface &$context);

	/**
	 * Saves data of all contexts in the current scope to the scopes storage
	 * @return AbstractContextScope
	 */
	abstract public function save_contexts();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::get_workbench()
	 */
	public function get_workbench(){
		return $this->exface;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::get_context_manager()
	 */
	public function get_context_manager(){
		return $this->get_workbench()->context();
	}
	
	/**
	 * Returns a unique identifier of the context scope: e.g. the session id for window or session context, the user id
	 * for user context, the app alias for app contexts, etc. This id is mainly used as a key for storing information from
	 * the context (see session scope example).
	 * @return string
	 */
	public function get_scope_id(){
		return;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Contexts\ContextScopeInterface::get_name()
	 */
	public function get_name(){
		return $this->name;
	}
}
?>