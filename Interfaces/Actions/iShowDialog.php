<?php
namespace exface\Core\Interfaces\Actions;
use exface\Core\Widgets\Dialog;
interface iShowDialog {
	public function get_dialog_widget();
	
	/**
	 * The output of an action showing a widget is the widget instance
	 * @return Dialog
	 */
	public function get_result();
}