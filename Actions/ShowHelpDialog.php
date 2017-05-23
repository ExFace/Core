<?php namespace exface\Core\Actions;

use exface\Core\Widgets\Dialog;
use exface\Core\Exceptions\Actions\ActionCallingWidgetNotSpecifiedError;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Widgets\iHaveContextualHelp;
use exface\Core\Exceptions\Actions\ActionLogicError;

/**
 * This action opens a dialog with the auto-generated contextual help for the input widget of it's caller.
 * 
 * You can add a button calling this action to any widget that implements iHaveContextualHelp and it will open
 * a help-dialog for that widget. The contextual help is generated automatically from object and attribute
 * descriptions in the meta model. It will no contain anything if these descriptions are empty. * 
 * 
 * @author Andrej Kabachnik
 *
 */
class ShowHelpDialog extends ShowDialog {
	
	protected function init(){
		parent::init();
		$this->set_icon_name('help');
		$this->set_prefill_with_filter_context(false);
		$this->set_prefill_with_input_data(false);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Actions\ShowDialog::enhance_dialog_widget()
	 */
	protected function enhance_dialog_widget(Dialog $dialog){
		$dialog = parent::enhance_dialog_widget($dialog);
		
		// IMPORTANT: remove help button from the help dialog to prevent infinite help popups
		$dialog->set_hide_help_button(true);
		
		if ($this->get_called_by_widget() && $this->get_called_by_widget() instanceof iTriggerAction){
			if ($this->get_called_by_widget()->get_input_widget() instanceof iHaveContextualHelp){
				$this->get_called_by_widget()->get_input_widget()->get_help_widget($dialog);
			} else {
				throw new ActionLogicError($this, 'Calling widget cannot generate contextual help: id does not implement the interface iHaveContextualHelp!', '6V9XDV4');
			}
		} else {
			throw new ActionCallingWidgetNotSpecifiedError($this, 'No calling widget passed to action "' . $this->get_alias_with_namespace() . '"!', '6V9XDV4');
		}
		return $dialog;
	}
}
?>