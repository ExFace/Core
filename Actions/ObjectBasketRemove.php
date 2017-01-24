<?php namespace exface\Core\Actions;

/**
 * Removes meta object instances matching the input data from the object basket in the given context scope (window scope by default)
 * 
 * @author Andrej Kabachnik
 *
 */
class ObjectBasketRemove extends ObjectBasketFetch {
	
	protected function init(){
		parent::init();
		$this->set_input_rows_min(1);
		$this->set_input_rows_max(null);
		$this->set_icon_name('remove');
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
}
?>