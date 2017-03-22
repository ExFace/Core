<?php namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iShowDialog;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\Widgets\Dialog;
use exface\Core\Interfaces\Widgets\iHaveIcon;

class ShowDialog extends ShowWidget implements iShowDialog {
	private $include_headers = true;
	private $dialog_widget = null;
	private $widget_was_enhanced = false;
	
	/**
	 * Creates the dialog widget. If not contents is passed, an empty dialog widget will be returned.
	 * 
	 * This method is called if there is no widget passed to the action or the passed widget is not a dialog.
	 * It creates a basic dialog and optionally fills it with the given content. By overriding this method,
	 * you can change the way non-dialog widgets are handled. To fill a dialog with default widgets, add
	 * buttons, etc. override enhance_dialog_widget() instead.
	 * 
	 * @see enhance_dialog_widget()
	 * @return \exface\Core\Widgets\Dialog
	 */
	protected function create_dialog_widget(AbstractWidget $contained_widget = NULL){
		/* @var $dialog \exface\Core\Widgets\Dialog */
		$parent_widget = $this->get_called_by_widget();
		$dialog = $this->get_called_on_ui_page()->create_widget('Dialog', $parent_widget);
		$dialog->set_meta_object($this->get_meta_object());
		
		if ($contained_widget){
			$dialog->add_widget($contained_widget);
		}
		
		return $dialog;
	}
	
	/**
	 * Adds some default attributes to a given dialog, that can be derived from the specifics of the action: 
	 * the dialog caption, icon, etc. These attributes can thus be ommited, when manually defining a dialog 
	 * for the action.
	 * 
	 * This method is called after the dialog widget had been instantiated - no matter how: from a UXON
	 * description passed to the action or automatically using create_dialog_widget(). This is the main
	 * difference to create_dialog_widget(), which is only called if no dialog was given.
	 * 
	 * Override this method to enhance the dialog even further: add widgets, buttons, etc.
	 * 
	 * @param Dialog $dialog
	 * @return \exface\Core\Widgets\Dialog
	 */
	protected function enhance_dialog_widget(Dialog $dialog){
		
		// If the widget calling the action (typically a button) is known, inherit some of it's attributes
		if ($this->get_called_by_widget()){
			if (!$dialog->get_icon_name() && ($this->get_called_by_widget() instanceof iHaveIcon)){
				$dialog->set_icon_name($this->get_called_by_widget()->get_icon_name());
			}
		} else {
			if(!$dialog->get_icon_name()){
				$dialog->set_icon_name($this->get_icon_name());
			}
		}
		
		if (!$dialog->get_caption()){
			$dialog->set_caption($this->get_dialog_caption());
		}
		
		return $dialog;
	}
	
	protected function get_dialog_caption(){
		if (!$caption = $this->get_name()){
			if ($this->get_called_by_widget()){
				$caption = $this->get_called_by_widget()->get_caption();
			}
		}
		return $caption;
	}
	
	/**
	 * The widget shown by ShowDialog is a dialog of course. However, specifying the entire dialog widget for custom dialogs is a lot of work,
	 * so you can also specify just the contents of the dialog in the widget property of the action in UXON. In this case, those widgets 
	 * specified there will be automatically wrapped in a dialog. This makes creating dialog easier and you can also reuse existing widgets,
	 * that are no dialogs (for example an entire page can be easily show in a dialog).
	 * 
	 * @see \exface\Core\Actions\ShowWidget::get_widget()
	 */
	public function get_widget(){
		$widget = parent::get_widget();
		if (!($widget instanceof Dialog)){
			$widget = $this->create_dialog_widget($widget);
			$this->set_widget($widget);
		} 
		
		if (!$this->widget_was_enhanced){
			$widget = $this->enhance_dialog_widget($widget);
			$this->widget_was_enhanced = true;
		}
		return $widget;
	}
	
	public function get_dialog_widget(){
		return $this->get_widget();
	}
	
	/**
	 * The output for action showing dialogs is either the rendered contents of the dialog (if lazy loading is enabled) 
	 * or the rendered dialog itself.
	 * 
	 * FIXME Remove outputting only the content of the dialog for ajax requests once all templates moved to fetching entire dialogs!
	 * 
	 * @see \exface\Core\Actions\ShowWidget::get_result_output()
	 */
	public function get_result_output(){
		$dialog = $this->get_result();
		if ($dialog->get_lazy_loading() && !$this->get_template()->is('exface.AdminLteTemplate')){
			$code = $this->get_app()->get_workbench()->ui()->draw($dialog->get_contents_container());
		} else {
			$this->get_result()->set_lazy_loading(false);
			if ($this->get_include_headers()){
				$code = $this->get_template()->draw_headers($this->get_result());
			}
			$code .= parent::get_result_output();
		}
		return $code;
	} 
	
	public function get_include_headers() {
		return $this->include_headers;
	}
	
	public function set_include_headers($value) {
		$this->include_headers = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}
	
	  
}
?>