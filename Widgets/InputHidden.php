<?php
namespace exface\Core\Widgets;
class InputHidden extends Input {

	protected function init(){
		parent::init();
		$this->set_hidden(true);
		$this->set_visibility(EXF_WIDGET_VISIBILITY_HIDDEN);
	}
	
}
?>