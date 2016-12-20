<?php namespace exface\Core\Behaviors;

class StateMachineState {
	
	private $state_id = null;
	private $buttons = null;
	private $disabled_attributes = null;
	
	public function get_state_id() {
		return $this->state_id;
	}
	
	public function set_state_id($value) {
		$this->state_id = $value;
		return $this;
	}
	
	public function get_buttons() {
		return $this->buttons;
	}
	
	public function set_buttons($value) {
		$this->buttons = $value;
		return $this;
	}
	
	public function get_disabled_attributes() {
		return $this->disabled_attributes;
	}
	
	public function set_disabled_attributes($value) {
		$this->disabled_attributes = $value;
		return $this;
	}
}
?>
