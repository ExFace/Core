<?php namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Widgets\Dialog;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Button;

class EditObjectDialog extends ShowObjectDialog {
	private $save_action_alias = null;
	
	protected function init(){
		parent::init();
		$this->set_icon_name('edit');
		$this->set_save_action_alias('exface.Core.UpdateData');
		$this->set_show_only_editable_attributes(true);
		$this->set_disable_editing(false);
	}
	
	/**
	 * Create editors for all editable attributes of the object
	 * @return WidgetInterface[]
	 */
	protected function create_editors(AbstractWidget $parent_widget){		
		return parent::create_widgets_for_attributes($parent_widget);
	}
	
	/**
	 * Creates the dialog widget. Just the dialog itself, no contents!
	 * @return \exface\Core\Widgets\exfDialog
	 */
	protected function create_dialog_widget(AbstractWidget $contained_widget = NULL){
		$dialog = parent::create_dialog_widget();
		$page = $this->get_called_on_ui_page();
		// TODO add save button via followup actions in the init() method instead of the button directly
		/* @var $save_button \exface\Core\Widgets\Button */
		$save_button = $page->create_widget('DialogButton', $dialog);
		$save_button->set_action_alias($this->get_save_action_alias());
		$save_button->set_caption($this->get_workbench()->get_core_app()->get_translator()->translate("ACTION.EDITOBJECTDIALOG.SAVE_BUTTON"));
		$save_button->set_visibility(EXF_WIDGET_VISIBILITY_PROMOTED);
		// Make the save button refresh the same widget as the Button showing the dialog would do
		if ($this->get_called_by_widget() instanceof Button){
			$save_button->set_refresh_widget_link($this->get_called_by_widget()->get_refresh_widget_link());
			$this->get_called_by_widget()->set_refresh_widget_link(null);
		}
		$dialog->add_button($save_button);
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
	
}
?>