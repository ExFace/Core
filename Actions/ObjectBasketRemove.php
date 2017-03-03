<?php namespace exface\Core\Actions;

/**
 * Removes meta object instances matching the input data from the object basket in the given context scope (window scope by default)
 * 
 * @author Andrej Kabachnik
 *
 */
class ObjectBasketRemove extends ObjectBasketFetch {
	private $return_basket_content = null;
	
	protected function init(){
		parent::init();
		$this->set_input_rows_min(1);
		$this->set_input_rows_max(null);
		$this->set_icon_name('remove');
	}
	
	protected function perform(){
		$input = $this->get_input_data_sheet();
		$object = $input->get_meta_object();
		if ($input->is_empty()){
			$this->get_context()->remove_instances_for_object_id($object->get_id());
			$this->set_result_message($this->get_workbench()->get_core_app()->get_translator()->translate('ACTION.OBJECTBASKETREMOVE.RESULT_ALL', array('%object_name%' => $object->get_name())));
		} else {
			$removed = 0;
			foreach ($input->get_uid_column()->get_values(false) as $uid){
				$this->get_context()->remove_instance($object->get_id(), $uid);
				$removed++;
			}
			$this->set_result_message($this->get_workbench()->get_core_app()->get_translator()->translate('ACTION.OBJECTBASKETREMOVE.RESULT', array('%number%' => $removed, '%object_name%' => $object->get_name()), $removed));
		}
		if ($this->get_return_basket_content()){
			$this->set_result($this->get_favorites_json());
		} else {
			$this->set_result('');
		}
	}
	
	public function get_return_basket_content(){
		if (is_null($this->return_basket_content)){
			$this->return_basket_content = $this->get_workbench()->get_request_param('fetch') ? true : false;
		}
		return $this->return_basket_content;
	}
	
	public function set_return_basket_content($value){
		$this->return_basket_content = filter_var($value, FILTER_VALIDATE_BOOLEAN);
		return $this;
	}
}
?>