<?php
namespace exface\Core\Actions;
class CreateObjectDialog extends EditObjectDialog {
	
	protected function init(){
		parent::init();
		$this->set_input_rows_min(null);
		$this->set_input_rows_max(null);
		$this->set_icon_name('add');
		$this->set_save_action_alias('exface.Core.CreateData');
		// Do not prefill with input data because we will be creating a new object in any case - regardless of the input data.
		// We can still make prefills setting widget values directly in UXON. Automatic prefills from the context can also be used.
		$this->set_prefill_with_input_data(false);
	}
		
	protected function perform(){
		$this->prefill_widget();
		$this->set_result_data_sheet($this->get_input_data_sheet());
		$this->set_result($this->get_widget());
	}
}
?>