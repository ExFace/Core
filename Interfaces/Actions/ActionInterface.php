<?php namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\UiPageInterface;
use exface\Core\Interfaces\TemplateInterface;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;
use exface\Core\Exceptions\Actions\ActionInputError;
use exface\Core\Interfaces\iCanBeCopied;

interface ActionInterface extends ExfaceClassInterface, AliasInterface, iCanBeCopied {
	
	/**
	 * @return string
	 */
	public function get_id();
	
	/**
	 * @return AppInterface
	 */
	public function get_app();
	
	/**
	 * @return string
	 */
	public function get_icon_name();
	
	/**
	 * 
	 * @param string $value
	 * @return ActionInterface
	 */
	public function set_icon_name($value);
	
	/**
	 * Sets the alias of the action. The alias must be unique within the app!
	 * 
	 * @param string $value
	 * @return ActionInterface
	 */
	public function set_alias($value);
	
	/**
	 * Returns the widget, that called the action (typically a button) or null, if the action was called internally or via AJAX.
	 * @return WidgetInterface
	 *
	 * IDEA Returning NULL in certain cases does not feel right. We had to add the called_by_widget() method to be able to determine the meta_object
	 * of the dialog even if the action does not have an input data sheet yet (when drawing the dialog in ajax templates). At that point,
	 * the action does not know, what object it is going to be performed upon. I don't feel comfortable with this solution though, since called_by_widget
	 * will be null when performing the action via AJAX (or the entire page would need to be instantiated).
	 * Here are the choices I had:
	 * - I could create the Dialog when the action is really called an import the entire dialog via AJAX.
	 * - I could also pass the meta object as a separate parameter to the action:
	 *		action->set_target_meta_object() - may be a good idea since an action could also have a built it meta_object, which should not be overridden
	 *   	or action->set_called_by_widget  - enables the action to create widgets with real parents, but produces overhead whe called via AJAX and
	 *      is not needed for actions within workflows (or is it?)
	 */
	public function get_called_by_widget();
	
	/**
	 * Sets the widget, that called the action: either taking an instantiated widget object or a widget link (text, uxon or object)
	 * @param AbstractWidget || WidgetLink || string $widget_or_widget_link
	 * @return ActionInterface
	 */
	public function set_called_by_widget($widget_or_widget_link);
	
	/**
	 * @return ActionInterface[]
	 */
	public function get_followup_actions();
	
	/**
	 * 
	 * @param ActionInterface[] $actions_array
	 */
	public function set_followup_actions(array $actions_array);
	
	/**
	 * 
	 * @param ActionInterface $action
	 * @return ActionInputInterface
	 */
	public function add_followup_action(ActionInterface $action);
	
	/**
	 * Returns the resulting data sheet. Performs the action if it had not been performed yet.
	 * @return DataSheetInterface
	 */
	public function get_result_data_sheet();
	
	/**
	 * Returns the result of the action - whatever it is. What data type is returned depends on the specific action implementation.
	 * In any case, get_result() makes sure, the action is performed.
	 * In contrast to get_result_data_sheet(), the get_result() methods can return anything. While get_result_data_sheet() is important
	 * for concatennation of actions and actually performing them, the output is whatever the user actually sees and, perhaps even more importantly,
	 * whatever is compared to there reference when testing actions.
	 * @return mixed
	 */
	public function get_result();
	
	/**
	 * Returns a string representing the result object of the action (= a string version of get_result())
	 * @return string
	 */
	public function get_result_stringified();
	
	/**
	 * Returns a printable version of the result: HTML or text if the result is a widget, UXON for data sheets, etc.
	 * By default, it's the UXON of the result data sheet
	 * @return string
	 */
	public function get_result_output();
	
	/**
	 * Returns a human readable message, describing, what the action has done.
	 * @return string
	 */
	public function get_result_message();
	
	/**
	 * Sets the data sheet, the action is supposed to be performed upon.
	 * @param DataSheet || UxonObject || string $data_sheet_or_uxon
	 * @throws ActionInputError if the passed input data is of an unsupported type
	 * @return \exface\Core\Interfaces\Actions\ActionInterface
	 */
	public function set_input_data_sheet($data_sheet_or_uxon);
	
	/**
	 * Returns the data sheet, the action is performed upon. It remains untouched even after
	 * the action is performed, so you can always return to the input data.
	 * @return DataSheetInterface
	 */
	public function get_input_data_sheet();
	
	/**
	 * Returns the minimum number of rows the action expects in the input data sheet.
	 * @return integer
	 */
	public function get_input_rows_min();
	
	/**
	 * 
	 * @param integer $value
	 * @return ActionInterface
	 */
	public function set_input_rows_min($value);
	
	/**
	 * Returns the maximum number of rows the action expects in the input data sheet.
	 * @return integer
	 */
	public function get_input_rows_max();
	
	/**
	 * 
	 * @param integer $value
	 * @return ActionInterface
	 */
	public function set_input_rows_max($value);
	
	/**
	 * Returns the meta object, the action is performed upon. The meta object is generally determined
	 * from the input data sheet, because this is the data the action is performed with. If not input
	 * data is set, the meta object of the calling widget is used because it is most likely, that it's
	 * data will be the input (i.e. after an ajax-request).
	 * @throws ActionObjectNotSpecifiedError if neither input data nor calling widget are known
	 * @return Object
	 */
	public function get_meta_object();
	
	/**
	 * 
	 * @param Object $object
	 * @return ActionInterface
	 */
	public function set_meta_object(Object $object);
	
	/**
	 * 
	 * @param unknown $qualified_alias
	 * @return ActionInputInterface
	 */
	public function set_object_alias($qualified_alias);
	
	/**
	 * 
	 * @param string $interface
	 * @return boolean
	 */
	public function implements_interface($interface);
	
	/**
	 * Returns TRUE, if the action can be undone or FALSE otherwise. An action may override this method for a more complex algorithm
	 * to determine, if it can be undone. In particular, the result may vary depending on the current application state: a complex action
	 * may become not undoable after performing som checks on the actual data, while it would be undoable by default.
	 * @return boolean
	 */
	public function is_undoable();
	
	/**
	 *
	 * @param boolean $value
	 * @return \exface\Core\Interfaces\Actions\ActionInterface
	 */
	public function set_undoable($value);
	
	/**
	 * Returns TRUE, if the action modifies data in a data source or FALSE otherwise. By default all actions capable of modifying data return TRUE,
	 * but the flag may change, if there had been no data actually modified while performing the action. Assuming TRUE if a data modification is
	 * possible, makes sure, no modifications actually remains undiscovered because of developers forgetting to set the appropriate flag of an action.
	 * @return boolean
	 */
	public function is_data_modified();
	
	/**
	 *
	 * @param boolean $value
	 * @return \exface\Core\Interfaces\Actions\ActionInterface
	 */
	public function set_data_modified($value);
	
	/**
	 * 
	 * @param array $behavior_aliases
	 */
	public function set_disabled_behaviors(array $behavior_aliases);
	
	/**
	 * @return array
	 */
	public function get_disabled_behaviors();
	
	/**
	 * @return UiPageInterface
	 */
	public function get_called_on_ui_page();
	
	/**
	 * @return TemplateInterface
	 */
	public function get_template();
	
	/**
	 * @return string
	 */
	public function get_template_alias();
	
	/**
	 * 
	 * @param string $value
	 * @return \exface\Core\Interfaces\Actions\ActionInterface
	 */
	public function set_template_alias($value);
	
	/**
	 * Returns the default name of the action translated to the currently used locale.
	 * @return string
	 */
	public function get_name();
	
	/**
	 * 
	 * @param string $value
	 * @return ActionInterface
	 */
	public function set_name($value);
}

?>
