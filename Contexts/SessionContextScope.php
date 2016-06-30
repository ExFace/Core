<?php namespace exface\Core\Contexts;

use exface\Core\Interfaces\Contexts\ContextInterface;

class SessionContextScope extends AbstractContextScope {
	/**
	 * TODO
	 * @see \exface\Core\Contexts\AbstractContextScope::load_contexts()
	 */
	public function load_context_data(ContextInterface &$context){
		
	}
	
	/**
	 * TODO
	 * @see \exface\Core\Contexts\AbstractContextScope::save_contexts()
	 */
	public function save_contexts(){
		
	}
	
	/**
	 * 
	 * @see \exface\Core\Contexts\AbstractContextScope::get_scope_id()
	 */
	public function get_scope_id(){
		return session_id();
	}
	
}
?>