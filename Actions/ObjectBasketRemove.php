<?php namespace exface\Core\Actions;

use exface\Core\Contexts\Types\ObjectBasketContext;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class ObjectBasketRemove extends ObjectBasketFetch {
	
	protected function init(){
		parent::init();
		$this->set_input_rows_min(1);
		$this->set_input_rows_max(null);
		$this->set_icon_name('star');
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
		if ($input->is_empty()){
			$this->get_context()->remove_instances_for_object_id($object->get_id());
		} else {
			// TODO remove single instances
		}
		$this->set_result($this->get_favorites_json());
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Actions\SetContext::get_context()
	 * @return ObjectBasketContext
	 */
	public function get_context(){
		return parent::get_context();
	}
}
?>