<?php
namespace exface\Core\Widgets;
class EditMatrix extends DataMatrixOld { 
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