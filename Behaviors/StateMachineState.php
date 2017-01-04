<?php namespace exface\Core\Behaviors;

/**
 * 
 * @author SFL
 *
 */
class StateMachineState {
	
	private $state_id = null;
	private $buttons = [];
	private $disabled_attributes = [];
	
	/**
	 * 
	 * @return unknown
	 */
	public function get_state_id() {
		return $this->state_id;
	}
	
	/**
	 * 
	 * @param unknown $value
	 * @return \exface\Core\Behaviors\StateMachineState
	 */
	public function set_state_id($value) {
		$this->state_id = $value;
		return $this;
	}
	
	/**
	 * 
	 * @return unknown
	 */
	public function get_buttons() {
		return $this->buttons;
	}
	
	/**
	 * 
	 * @param unknown $value
	 * @return \exface\Core\Behaviors\StateMachineState
	 */
	public function set_buttons($value) {
		$this->buttons = $value;
		return $this;
	}
	
	/**
	 * 
	 * @return unknown
	 */
	public function get_disabled_attributes() {
		return $this->disabled_attributes;
	}
	
	/**
	 * 
	 * @param unknown $value
	 * @return \exface\Core\Behaviors\StateMachineState
	 */
	public function set_disabled_attributes($value) {
		$this->disabled_attributes = $value;
		return $this;
	}
}
?>
