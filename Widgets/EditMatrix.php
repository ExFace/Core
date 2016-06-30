<?php
namespace exface\Widgets;
class EditMatrix extends DataMatrix { 
	private $formulas_enabled = true;
	
	public function get_formulas_enabled() {
		return $this->formulas_enabled;
	}
	
	public function set_formulas_enabled($value) {
		$this->formulas_enabled = $value;
		return $this;
	}  
}
?>