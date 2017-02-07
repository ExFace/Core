<?php namespace exface\Core\Actions;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\DataSheetFactory;

/**
 * Fetches meta object instances stored in the object basket of the specified context_scope (by default, the window scope)
 * 
 * @author Andrej Kabachnik
 *
 */
class ObjectBasketFetch extends ObjectBasketAdd {
	const OUTPUT_TYPE_JSON = 'JSON';
	const OUTPUT_TYPE_DIALOG = 'DIALOG';
	
	private $output_type = null;
	
	protected function perform(){
		if ($this->get_output_type() == static::OUTPUT_TYPE_DIALOG){
			if ($this->get_template()->get_request_object_id()){
				$meta_object = $this->get_workbench()->model()->get_object($this->get_template()->get_request_object_id());
			}
			$this->set_result($this->build_dialog($meta_object));
		} else {
			$this->set_result($this->get_favorites_json());
		}
	}
	
	protected function get_favorites_json(){
		$result = array();
		foreach ($this->get_context()->get_favorites_all() as $fav_list){
			$result[] = array(
					'object_id' => $fav_list->get_meta_object()->get_id(),
					'object_name' => $fav_list->get_meta_object()->get_name(),
					'object_actions' => $this->build_json_actions($fav_list->get_meta_object()),
					'instances' => $fav_list->export_uxon_object()
			);
			
		}
		return json_encode($result);
	}
	
	protected function build_json_actions(Object $object){
		$result = array();
		foreach($object->get_actions()->get_used_in_object_basket() as $a){
			$result[] = array(
				'name' => $a->get_name(),
				'alias' => $a->get_alias_with_namespace()
			);
		}
		return $result;
	}
	
	protected function build_dialog(Object $meta_object){
		/* @var $dialog \exface\Core\Widgets\Dialog */
		$dialog = WidgetFactory::create($this->get_called_on_ui_page(), 'Dialog');
		$dialog->set_id('object_basket');
		$dialog->set_meta_object($meta_object);
		$dialog->set_caption('Object basket');
		$dialog->set_lazy_loading(false);
		
		/* @var $table \exface\Core\Widgets\DataTable */
		$table = WidgetFactory::create($dialog->get_page(), 'DataTable', $dialog);
		$table->set_lazy_loading(false);
		$table->set_paginate(false);
		$table->set_hide_toolbar_bottom(true);
		$table->set_multi_select(true);
		$prefill_sheet = DataSheetFactory::create_from_object($meta_object);
		foreach ($this->get_context()->get_favorites_by_object($meta_object)->get_all() as $instance){
			$prefill_sheet->add_row($instance->export_uxon_object()->to_array());
		}
		$table->prefill($prefill_sheet);
		$dialog->add_widget($table);
		
		foreach($meta_object->get_actions()->get_used_in_object_basket() as $a){
			/* @var $button \exface\Core\Widgets\Button */
			$button = WidgetFactory::create($dialog->get_page(), 'Button', $dialog);
			$button->set_action($a);
			$button->set_align(EXF_ALIGN_LEFT);
			$button->set_input_widget($table);
			$dialog->add_button($button);
		}
		
		return $this->get_template()->draw($dialog);
	}
	
	public function get_output_type() {
		if (is_null($this->output_type)){
			if ($type = $this->get_workbench()->get_request_param('output_type')){
				$this->set_output_type($type);
			} else {
				$this->output_type = static::OUTPUT_TYPE_JSON;
			}
		}
		return $this->output_type;
	}
	
	public function set_output_type($value) {
		$const = 'static::OUTPUT_TYPE_' . mb_strtoupper($value);
		if (!defined($const)){
			throw new ActionConfigurationError($this, 'Invalid value "' . $value . '" for option "output_type" of action "' . $this->get_alias_with_namespace() . '": use "JSON" or "DIALOG"!');
		}
		$this->output_type = constant($const);
		return $this;
	}
	
	  

}
?>