<?php
namespace exface\Widgets;
class Form extends Panel {
	private $method = 'POST'; // FIXME DEPRECATED should be moved to the templates
	private $submit_action = ''; // FIXME DEPRECATED should depend on the button pressed (buttons have own actions!)
	private $sumbit_action_input = ''; // FIXME DEPRECATED The input string used in action->perform() lager

	public function get_method() {
		return $this->method;
	}
	
	public function set_method($value) {
		$this->method = $value;
	}	  
	
	public function get_submit_action() {
		return $this->submit_action;
	}
	
	public function set_submit_action($value) {
		$this->submit_action = $value;
	}
	
	public function get_submit_action_input() {
		return $this->submit_action_input;
	}
	
	public function set_submit_action_input($value) {
		$this->submit_action_input = $value;
	}	  
}
?>