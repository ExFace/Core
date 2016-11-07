<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iTakeInput;

class Input extends Text implements iTakeInput {
	private $required = null;
	private $validator = null;
	
	public function get_validator() {
		return $this->validator;
	}
	
	public function set_validator($value) {
		$this->validator = $value;
	}
	
	/**
	 * {@inheritDoc}
	 * Input widgets are considered as required if they are explicitly marked as such or if the represent a meta attribute, 
	 * that is a required one.
	 * @see \exface\Core\Interfaces\Widgets\iTakeInput::is_required()
	 */
	public function is_required() {
		if (is_null($this->required)){
			if ($this->get_attribute()){
				return $this->get_attribute()->is_required();
			} else {
				return false;
			}
		}
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