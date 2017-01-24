<?php namespace exface\Core\Actions;

use exface\Core\Contexts\Types\ObjectBasketContext;
use exface\Core\Exceptions\Actions\ActionInputMissingError;

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
		$counter = 0;
		$input = $this->get_input_data_sheet();
		$object = $input->get_meta_object();
		foreach ($input->get_rows() as $row){
			$uid = $row[$object->get_uid_alias()];
			if (!$uid){
				throw new ActionInputMissingError($this, 'Cannot add object "' . $object->get_alias_with_namespace() . '" to favorites: missing UID-column "' . $object->get_uid_alias() . '"!', '6TMQR5N');
			}
			$label = $row[$object->get_label_alias()];
			if (!$label){
				// TODO fetch label from data source with a simple data sheet
			}
			$this->get_context()->add_instance($object->get_id(), $uid, $label);
			$counter++;
		}
		$this->set_result_message($this->translate('RESULT', array('%number%' => $counter), $counter));
	}
}
?>