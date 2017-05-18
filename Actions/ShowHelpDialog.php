<?php namespace exface\Core\Actions;

use exface\Core\Widgets\Dialog;
use exface\Core\Exceptions\Actions\ActionCallingWidgetNotSpecifiedError;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Widgets\iHaveContextualHelp;

class ShowHelpDialog extends ShowDialog {
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Actions\ShowDialog::enhance_dialog_widget()
	 */
	protected function enhance_dialog_widget(Dialog $dialog){
		$dialog = parent::enhance_dialog_widget($dialog);
		if ($this->get_called_by_widget() && $this->get_called_by_widget() instanceof iTriggerAction){
			if ($this->get_called_by_widget()->get_input_widget() instanceof iHaveContextualHelp){
				$this->get_called_by_widget()->get_input_widget()->get_help_widget($dialog);
			} else {
				// TODO throw exception
			}
		} else {
			throw new ActionCallingWidgetNotSpecifiedError($this, 'Cannot generate a help widget: no calling widget passed to action!');
		}
		return $dialog;
	}
	
	/*protected function get_dialog_caption(){
		if (!$caption = $this->get_name()){
			if ($this->get_called_by_widget()){
				$caption = $this->get_called_by_widget()->get_caption();
			}
		}
		return $caption;
	}*/
}
?>