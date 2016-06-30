<?php namespace exface\Apps\exface\Core\Actions;

use exface\Core\Interfaces\Actions\iNavigate;
use exface\Core\AbstractAction;

class GoBack extends AbstractAction implements iNavigate {
	protected function init(){
		$this->set_icon_name('back');
	}
	
	protected function perform(){
		$this->set_result_data_sheet($this->get_input_data_sheet());
	}
}
?>