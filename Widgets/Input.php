<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iTakeInput;

class Input extends Text implements iTakeInput {
	protected $required = false;
	private $validator = null;
	
	public function get_validator() {
		return $this->validator;
	}
	
	public function set_validator($value) {
		$this->validator = $value;
	}
	
	public function is_required() {
		return $this->required;
	}
	
	public function set_required($value) {
		$this->required = $value;
	}
	
	/**
	 * Input widgets are disabled if the displayed attribute is not editable or if the widget was explicitly disabled.
	 * @see \exface\Core\Widgets\AbstractWidget::is_disabled()
	 */
	public function is_disabled(){
		$disabled = parent::is_disabled();
		if (is_null($disabled)){
			if ($this->get_attribute() && !$this->get_attribute()->is_editable()){
				$disabled = true;
			} else {
				$disabled = false;
			}
		}
		return $disabled;
	}
}
?>