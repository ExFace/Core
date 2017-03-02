<?php namespace exface\Core\Actions;

use exface\Core\Contexts\Types\ObjectBasketContext;

/**
 * Adds the input rows to the object basket in a specified context_scope (by default, the window scope)
 * 
 * @method ObjectBasketContext get_context()
 * 
 * @author Andrej Kabachnik
 *
 */
class ObjectBasketAdd extends SetContext {
	
	protected function init(){
		parent::init();
		$this->set_input_rows_min(1);
		$this->set_input_rows_max(null);
		$this->set_icon_name('basket');
		$this->set_context_type('ObjectBasket');
	}	

	public function get_scope(){
		if (!parent::get_scope()){
			$this->set_scope('Window');
		}
		return parent::get_scope();
	}
	
	protected function perform(){
		$this->get_context()->add($this->get_input_data_sheet());
		$this->set_result_message($this->translate('RESULT', array('%number%' => $this->get_input_data_sheet()->count_rows()), $this->get_input_data_sheet()->count_rows()));
		$this->set_result('');
	}
}
?>