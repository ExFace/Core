<?php namespace exface\Core\Contexts\Scopes;

use exface\Core\Exceptions\ContextError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Contexts\ContextInterface;

/**
 * The window scope exists in every browser window separately (in contrast to the session scope, which is bound to a single "user login").
 * CAUTION: Keep in mind, that concurrent ajax requests from the same window will use the same window scope, so the request that gets finished last
 * will actually overwrite data from the other requests.
 * 
 * TODO Currently the window session scope includes all windows of a browser - fix this by sending a session id with each request
 * 
 * @author Andrej Kabachnik
 *
 */
class WindowContextScope extends AbstractContextScope {
	private $session_id = null;
	
	/**
	 * Since the window context ist stored in the $_SESSION, init() makes sure, the session is available and tries to
	 * instantiate all contexts saved there. Thus, the window contexts are always loaded on startup, not only once they are
	 * actually used. This should be OK, since window contexts will probably be used in every single request.
	 * @see \exface\Core\Contexts\Scopes\AbstractContextScope::init()
	 */
	protected function init(){
		
		$this->session_open();
		
		if (is_array($this->get_saved_contexts())){
			foreach ($this->get_saved_contexts() as $alias => $uxon){
				try {
					$this->get_context($alias);
				} catch (ContextError $error){
					$this->remove_context($alias);
				}
			}
		}
		
		// It is important to save the session once we have read the data, because otherwise it will block concurrent ajax-requests
		$this->session_close();
		
		return parent::init();
	}
	
	/**
	 * Since the window context ist stored in the $_SESSION, loading contexts simply fetches the contents
	 * of the contexts array in the $_SESSION variable and tries to parse it as a UXON object.
	 * @see \exface\Core\Contexts\Scopes\AbstractContextScope::load_context_data()
	 */
	public function load_context_data(ContextInterface &$context){
		// Check to see if the session had been started by some other code (e.g. the CMS, etc.)
		// If not, start it here!
		
		if ($this->get_saved_contexts($context->get_alias())){
			$context->import_uxon_object($this->get_saved_contexts($context->get_alias()));
		}
		return $this;
	}
	
	/**
	 * @see \exface\Core\Contexts\Scopes\AbstractContextScope::get_saved_contexts()
	 * @return \exface\Core\CommonLogic\UxonObject
	 */
	public function get_saved_contexts($context_alias = null){
		
		if ($context_alias){
			$obj = $this->get_session_data()[$context_alias];
		} else {
			$obj = $this->get_session_data();
		}
		
		return UxonObject::from_anything($obj);
	}
	
	/**
	 * The window scope saves all it's contexts as UXON objects in the $_SESSION
	 * @see \exface\Core\Contexts\Scopes\AbstractContextScope::save_contexts()
	 */
	public function save_contexts(){
		//var_dump($_SESSION);
		$this->session_open();
		
		foreach($this->get_all_contexts() as $context){
			$uxon = $context->export_uxon_object();
			if (!is_null($uxon) && !$uxon->is_empty()){
				// Save the context in the session in JSON-Representation, because saving it directly as a UxonObject
				// causes errors when reading the session: all used classes must be declared (included) before the
				// session is initialized. So as long as we are using the CMS session here, we can only store built-in
				// types. If ExFace will create own sessions, this can be changed!
				$this->set_session_data($context->get_alias(), $uxon->to_json());
			} else {
				$this->remove_context($context->get_alias());
			}
		}
		
		// It is important to save the session once we have read the data, because otherwise it will block concurrent ajax-requests
		$this->session_close();
		
		return $this;
	}
	
	public function remove_context($alias){
		unset($_SESSION['exface']['contexts'][$alias]);
		return $this;
	}
	
	/**
	 * TODO The session id should get somehow bound to a window, since the window context scope only exists in a
	 * specific instance of ExFace in contrast to the session context scope, which actually is quite like the php session!
	 * {@inheritDoc}
	 * @see \exface\Core\Contexts\Scopes\AbstractContextScope::get_scope_id()
	 */
	public function get_scope_id(){
		return $this->get_session_id();
	}
	
	protected function set_session_id($string){
		$this->session_id = $string;
		return $this;
	}
	
	protected function get_session_id(){
		return $this->session_id;
	}
	
	/**
	 * Opens the curernt session for writing. Creates a new session, if there is no session yet
	 * @return WindowContextScope
	 */
	protected function session_open(){
		if (!$this->session_is_open()){
			if ($this->session_id){
				session_name($this->session_id);
			}
			session_start();
		} else {
			$this->set_session_id(session_id());
		}
		return $this;
	}
	
	/**
	 * Closes the session, but does not empty the context data. This way, the session is not locked any more and can be used by
	 * other threads/processes
	 * @return WindowContextScope
	 */
	protected function session_close(){
		if (!$this->session_id){
			$this->set_session_id(session_id());
		}
		session_write_close();
		return $this;
	}
	
	/**
	 * Returns TRUE if the current session is open and active and FALSE otherwise
	 * @return boolean
	 */
	protected function session_is_open(){
		if ( php_sapi_name() !== 'cli' ) {
			if ( version_compare(phpversion(), '5.4.0', '>=') ) {
				return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
			} else {
				return session_id() === '' ? FALSE : TRUE;
			}
		}
		return FALSE;
	}
	
	/**
	 * Returns the raw array of context data from the current session
	 * @return array
	 */
	protected function get_session_data(){
		return $_SESSION['exface']['contexts'];
	}
	
	protected function set_session_data($key, $value){
		$_SESSION['exface']['contexts'][$key] = $value;
		return $this;
	}
	
}
?>