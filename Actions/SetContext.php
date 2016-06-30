<?php namespace exface\Apps\exface\Core\Actions;

use exface\Core\Contexts\AbstractContext;
use exface\Core\AbstractAction;

/**
 * This is the base action to modify context data.
 * 
 * @author aka
 *
 */
class SetContext extends AbstractAction {
	private $context_type = null;
	private $scope = null;
	
	public function get_context_type() {
		return $this->context_type;
	}
	
	public function set_context_type($value) {
		$this->context_type = $value;
		return $this;
	}
	
	public function get_scope() {
		return $this->scope;
	}
	
	public function set_scope($value) {
		$this->scope = $value;
		return $this;
	}
	
	/**
	 * Returns the context addressed in this action
	 * @return AbstractContext
	 */
	public function get_context(){
		return $this->get_app()->exface()->context()->get_scope($this->get_scope())->get_context($this->get_context_type());
	}
	
	protected function perform(){
		// TODO 
		return;
	}
}
?>