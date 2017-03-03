<?php namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\Dialog;

/**
 * Open a dialog to perform an advanced search for values for a specified input widget.
 * 
 * This action is very usefull for all sorts of select widgets. Although these often have internal search or autosuggest funcionality, 
 * it is often nessecary to search for a value using multiple filters, sorting, etc. This action will open a dialog with any search 
 * widget you like (the default table widget of the target object by default) and put the selected value into the target input or select
 * widget once the dialog is closed.
 * 
 * Basic Example:
 * {
 * 	"widget_type": "Form",
 * 	"object_alias" "my.app.ORDER"
 * 	"widgets": [
 * 		{
 * 			"widget_type": "ComboTable",
 * 			"attribute_alias": "CUSTOMER",
 * 			"id": "customer_selector"
 * 		}
 *  ],
 *  "buttons": [
 *  	{
 *  		"action": 
 *  			{
 *  				"alias": "exface.Core.ShowLookupDialog",
 *  				"object_alias": "my.app.CUSTOMER",
 *  				"target_widget_id": "customer_selector"
 *  			}
 *  	}
 *  ]
 * }
 * 
 * This action can be used with any widget, that accepts input.
 * 
 * @author Stefan Leupold
 *
 */
class ShowLookupDialog extends ShowDialog {
	
	private $target_widget_id = null;
	
	protected function init(){
		parent::init();
		$this->set_prefill_with_input_data(false);
		
		if ($this->get_called_by_widget() && $this->get_called_by_widget()->is('DialogButton')){
			$this->get_called_by_widget()->set_close_dialog_after_action_succeeds(false);
		}
	}
	
	protected function enhance_dialog_widget(Dialog $dialog){
		$dialog = parent::enhance_dialog_widget($dialog);
		$page = $this->get_called_on_ui_page();
		
		if ($dialog->count_widgets() == 0){
			$data_table = WidgetFactory::create($page, 'DataTable', $dialog);
			$data_table->set_meta_object($this->get_meta_object());
			$dialog->add_widget($data_table);
		} else {
			$data_table = reset($dialog->get_widgets());
		}
		
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
	 * @uxon-property target_widget_id
	 * @uxon-type string
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
