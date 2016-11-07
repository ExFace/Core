<?php namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Widgets\Dialog;
use exface\Core\Exceptions\MetaModelAttributeNotFoundException;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\WidgetFactory;

class EditObjectDialog extends ShowDialog {
	private $save_action_alias = null;
	private $show_only_editable_attributes = true;
	
	protected function init(){
		$this->set_input_rows_min(1);
		$this->set_input_rows_max(1);
		$this->set_icon_name('edit');
		$this->set_save_action_alias('exface.Core.UpdateData');
		// Disable prefilling the widget from contexts as we only whant to fill in data that actually comes from the data source
		$this->set_prefill_with_filter_context(false);
	}
	
	/**
	 * Create editors for all editable attributes of the object
	 * @return WidgetInterface[]
	 */
	protected function create_editors(AbstractWidget $parent_widget){
		$editors = array();
		$cnt = 0;
		/* @var $attr \exface\Core\CommonLogic\Model\attribute */
		foreach ($this->get_meta_object()->get_attributes() as $attr){
			$cnt++;
			// Ignore hidden attributes if they are not system attributes
			if ($attr->is_hidden()) continue;
			// Ignore not editable attributes if this feature is not explicitly disabled
			if (!$attr->is_editable() && $this->get_show_only_editable_attributes()) continue;
			// Ignore attributes with fixed values
			if ($attr->get_fixed_value()) continue;
			// Create the widget
			$ed = $this->create_widget_from_attribute($this->get_meta_object(), $attr->get_alias(), $parent_widget);
			if (method_exists($ed, 'set_required')) $ed->set_required($attr->is_required());
			if (method_exists($ed, 'set_disabled')) $ed->set_disabled(($attr->is_editable() ? false : true));
			$editors[] = $ed;
		}
		
		ksort($editors);
		
		return $editors;
	}
	
	function create_widget_from_attribute($obj, $attribute_alias, $parent_widget){		
		$attr = $obj->get_attribute($attribute_alias);
		if (!$attr){
			throw new MetaModelAttributeNotFoundException('Requested attribute "' . $attribute_alias . '" not found in object "' . $obj->get_alias() . '"');
		}
		$page = $this->get_called_on_ui_page();
		$widget = WidgetFactory::create_from_uxon($page, $attr->get_default_widget_uxon(), $parent_widget);
		$widget->set_attribute_alias($attribute_alias);
		$widget->set_caption($attr->get_name());
		$widget->set_hint($attr->get_hint());
		return $widget;
	}
	
	/**
	 * Creates the dialog widget. Just the dialog itself, no contents!
	 * @return \exface\Core\Widgets\exfDialog
	 */
	protected function create_dialog_widget(AbstractWidget $contained_widget = NULL){
		$dialog = parent::create_dialog_widget();
		// TODO add save button via followup actions in the init() method instead of the button directly
		/* @var $save_button \exface\Core\Widgets\Button */
		$save_button = $this->get_called_on_ui_page()->create_widget('DialogButton', $dialog);
		$save_button->set_action_alias($this->get_save_action_alias());
		$save_button->set_caption("Speichern");
		$save_button->set_visibility(EXF_WIDGET_VISIBILITY_PROMOTED);
		$dialog->add_button($save_button);
		if ($dialog->get_meta_object()->get_default_editor_uxon() && !$dialog->get_meta_object()->get_default_editor_uxon()->is_empty()){
			$page = $this->get_called_on_ui_page();
			$default_editor = WidgetFactory::create_from_uxon($page, $dialog->get_meta_object()->get_default_editor_uxon(), $dialog);
			$dialog->add_widget($default_editor);
		} else {
			$dialog->add_widgets($this->create_editors($dialog));
		}
		
		return $dialog;
	}
	
	public function set_dialog_widget(AbstractWidget $widget){
		$this->dialog_widget = $widget;
	}

	
	public function get_save_action_alias() {
		return $this->save_action_alias;
	}
	
	public function set_save_action_alias($value) {
		$this->save_action_alias = $value;
	} 
	
	/**
	 * Returns TRUE if only widgets for editable attributes should be shown or FALSE, if all visible widgets should appear (some being disabled).
	 * @return boolean
	 */
	public function get_show_only_editable_attributes() {
		return $this->show_only_editable_attributes;
	}
	
	public function set_show_only_editable_attributes($value) {
		$this->show_only_editable_attributes = $value;
		return $this;
	}  
}
?>