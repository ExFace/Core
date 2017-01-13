<?php namespace exface\Core\Contexts\Scopes;

use exface\Core\Interfaces\Contexts\ContextInterface;

/**
 * The window scope exists in every browser window separately (in contrast to the session scope, which is bound to a single "user login").
 * CAUTION: Keep in mind, that concurrent ajax requests from the same window will use the same window scope, so the request that gets finished last
 * will actually overwrite data from the other requests.
 * 
 * TODO Currently the window scope atually utilizes the session scope, so it does not really work correctly. It jus
 * delegates everything to the session scope for now. The idea is to fix this by sending a window specific session id 
 * with each request.
 * 
 * @author Andrej Kabachnik
 *
 */
class WindowContextScope extends AbstractContextScope {
	
	
	/**
	 * The window scope currently just delegates to the session scope, which actually takes care of saving and loading data
	 * @see \exface\Core\Contexts\Scopes\AbstractContextScope::save_contexts()
	 */
	public function save_contexts(){
		// Do nothing untill the windows scope is separated from the session scope
	}
	
	/**
	 * The window scope currently just delegates to the session scope, which actually takes care of saving and loading data
	 * @see \exface\Core\Contexts\Scopes\AbstractContextScope::load_context_data()
	 */
	public function load_context_data(ContextInterface $context){
		// Do nothing untill the windows scope is separated from the session scope
	}
	
	/**
	 * TODO The session id should get somehow bound to a window, since the window context scope only exists in a
	 * specific instance of ExFace in contrast to the session context scope, which actually is quite like the php session!
	 * For now we just return the session scopr id (session id) here.
	 * {@inheritDoc}
	 * @see \exface\Core\Contexts\Scopes\AbstractContextScope::get_scope_id()
	 */
	public function get_scope_id(){
		return $this->get_context_manager()->get_scope_session()->get_scope_id();
	}
	
	/**
	 * Delegate everything to the session scope until there is a proper implementation for the window scope
	 * {@inheritDoc}
	 * @see \exface\Core\Contexts\Scopes\AbstractContextScope::get_context()
	 */
	public function get_context($alias){
		return $this->get_context_manager()->get_scope_session()->get_context($alias);
	}
	
	/**
	 * Delegate everything to the session scope until there is a proper implementation for the window scope
	 * {@inheritDoc}
	 * @see \exface\Core\Contexts\Scopes\AbstractContextScope::get_all_contexts()
	 */
	public function get_all_contexts(){
		return $this->get_context_manager()->get_scope_session()->get_all_contexts();
	}
	
}
?>