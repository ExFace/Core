<?php namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Widgets\Dialog;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\DataTypes\BooleanDataType;

/**
 * This action will show a dialog displaying the default editor of a meta object in read-only mode.
 * 
 * Dialogs that show meta objects, will use the default editor description from the object's model, if specified.
 * If not, the action will generate a generic editor listing all widgets with their default editors or respective
 * generic editors of the corresponding data types.
 * 
 * If you do not specify a widget type in the object's default editor or set it to "Dialog", the UXON of the default
 * editor will be directly applied to the Dialog. If another widget type is specified, it will be treated as a separate
 * widget and added to the dialog as a child widget. Thus, if the default editor is 
 * 
 * {"widgets": [{...}, {...}], "caption": "My caption"}
 * 
 * the caption of the dialog will be set to "My caption" and all the widgets will get appended to the dialog. On the
 * other hand, the following default editor will produce a single tabs widget, which will be appended to the generic
 * dialog:
 * 
 * {"widget_type": "Tabs", "tabs": [...]}
 * 
 * If you choose to customize the dialog directly (first example), you can ommit the "widgets" array completely. This
 * will case the default editor widgets to get generated and appended to your custom dialog. This is an easy way to
 * add custom buttons, captions, etc. to generic dialogs.
 *
 * @author Andrej Kabachnik
 *
 */
class ShowObjectDialog extends ShowDialog {

	private $show_only_editable_attributes = null;
	private $disable_editing = true;
	
	protected function init(){
		$this->set_input_rows_min(1);
		$this->set_input_rows_max(1);
		$this->set_icon_name('info');
		$this->set_show_only_editable_attributes(false);
		// Disable prefilling the widget from contexts as we only whant to fill in data that actually comes from the data source
		$this->set_prefill_with_filter_context(false);
	}
	
	/**
	 * Create editors for all editable attributes of the object
	 * @return WidgetInterface[]
	 */
	protected function create_widgets_for_attributes(AbstractWidget $parent_widget){
		$editors = array();
		$cnt = 0;
		/* @var $attr \exface\Core\CommonLogic\Model\Attribute */
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
		$page = $this->get_called_on_ui_page();
		$widget = WidgetFactory::create_from_uxon($page, $attr->get_default_widget_uxon(), $parent_widget);
		$widget->set_attribute_alias($attribute_alias);
		$widget->set_caption($attr->get_name());
		$widget->set_hint($attr->get_hint());
		return $widget;
	}
	
	/**
	 * {@inheritDoc} 
	 * @see \exface\Core\Actions\ShowDialog::create_dialog_widget()
	 */
	protected function create_dialog_widget(AbstractWidget $contained_widget = NULL){
		$dialog = parent::create_dialog_widget();
		$page = $this->get_called_on_ui_page();
		$default_editor_uxon = $dialog->get_meta_object()->get_default_editor_uxon();
		if ($default_editor_uxon && !$default_editor_uxon->is_empty()){
			// If there is a default editor for an object, use it
			if (!$default_editor_uxon->get_property('widget_type') || $default_editor_uxon->get_property('widget_type') == 'Dialog'){
				$dialog->import_uxon_object($default_editor_uxon);
				if ($dialog->count_widgets() == 0){
					$dialog->add_widgets($this->create_widgets_for_attributes($dialog));
				}
			} else {
				$default_editor = WidgetFactory::create_from_uxon($page, $default_editor_uxon, $dialog);
				$dialog->add_widget($default_editor);
			}
		} else {
			// If there is no editor defined, create one: Add a panel to the dialog and generate editors for all attributes
			// of the object in that panel.
			// IDEA A separate method "create_object_editor" would probably be handy, once we have attribute groups and
			// other information, that would enable us to build better editors (with tabs, etc.)
			// FIXME Adding a form here is actually a workaround for wrong width calculation in the AdmnLTE template. It currently works only for forms there, not for panels.
			$panel = WidgetFactory::create($page, 'Form', $dialog);
			$panel->add_widgets($this->create_widgets_for_attributes($panel));
			$dialog->add_widget($panel);
		}
		return $dialog;
	}
	
	protected function enhance_dialog_widget(Dialog $dialog){
		$dialog = parent::enhance_dialog_widget($dialog);
		if ($this->get_disable_editing()){
			foreach ($dialog->get_input_widgets() as $widget){
				$widget->set_disabled(true);
			}
		}
		return $dialog;
	}
	
	public function set_dialog_widget(AbstractWidget $widget){
		$this->dialog_widget = $widget;
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
	
	public function get_disable_editing() {
		return $this->disable_editing;
	}
	
	public function set_disable_editing($value) {
		$this->disable_editing = BooleanDataType::parse($value);
		return $this;
	}
	
	
	  
}
?>