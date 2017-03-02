<?php namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\WidgetFactory;

class ShowLookupDialog extends ShowDialog {
	
	private $target_widget_id = null;
	
	protected function init(){
		parent::init();
		$this->set_prefill_with_input_data(false);
	}
	
	protected function create_dialog_widget(AbstractWidget $contained_widget = NULL) {
		$dialog = parent::create_dialog_widget();
		$page = $this->get_called_on_ui_page();
		
		$data_table = WidgetFactory::create($page, 'DataTable', $dialog);
		$data_table->set_object_alias($this->get_called_by_widget()->get_object_qualified_alias());
		$dialog->add_widget($data_table);
		
		// @var $save_button \exface\Core\Widgets\Button
		$save_button = $page->create_widget('DialogButton', $dialog);
		$save_button->set_caption($this->get_workbench()->get_core_app()->get_translator()->translate("ACTION.SHOWLOOKUPDIALOG.SAVE_BUTTON"));
		$save_button->set_visibility(EXF_WIDGET_VISIBILITY_PROMOTED);
		
		// @var $save_action \exface\Core\Actions\CustomTemplateScript
		$save_action = ActionFactory::create_from_string($this->get_workbench(), 'exface.Core.CustomTemplateScript', $save_button);
		$source_element = $this->get_template()->get_element($data_table);
		$target_element = $this->get_template()->get_element_by_widget_id($this->get_target_widget_id(), $page->get_id());
		$save_action_script = $target_element->build_js_value_setter($source_element->build_js_value_getter());  
		$save_action->set_script($save_action_script);
		
		$save_button->set_action($save_action);
		$dialog->add_button($save_button);
		
		return $dialog;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function get_target_widget_id() {
		return $this->target_widget_id;
	}
	
	/**
	 * The widget which should receive the selected values.
	 * 
	 * @param boolean $value
	 * @return \exface\Core\Actions\ShowLookupDialog
	 */
	public function set_target_widget_id($value) {
		$this->target_widget_id = $value;
		return $this;
	}
}

?>
