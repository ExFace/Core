<?php
namespace exface\Core\Widgets;
/**
 * A special type of button to use in dialogs. Additionally to the normal button functionality
 * this button can explicitly control the dialog it belongs to. Thus, the user can decide whether
 * the dialog is to be closed after the button's action is performed or not.
 * @author PATRIOT
 *
 */
class DialogButton extends Button {
	private $close_dialog_after_action_succeeds = true;
	private $close_dialog_after_action_fails = false;
	
	public function get_close_dialog_after_action_succeeds() {
		return $this->close_dialog_after_action_succeeds;
	}
	
	public function set_close_dialog_after_action_succeeds($value) {
		$this->close_dialog_after_action_succeeds = $value;
	}	

	public function get_close_dialog_after_action_fails() {
		return $this->close_dialog_after_action_fails;
	}
	
	public function set_close_dialog_after_action_fails($value) {
		$this->close_dialog_after_action_fails = $value;
	} 
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\Button::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = parent::export_uxon_object();
		$uxon->set_property('close_dialog_after_action_succeeds', $this->get_close_dialog_after_action_succeeds());
		$uxon->set_property('close_dialog_after_action_fails', $this->get_close_dialog_after_action_fails());
		return $uxon;
	}
}
?>