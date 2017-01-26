<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\Actions\iCanBeUndone;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\EventFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Events\ActionEvent;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\Actions\ActionOutputError;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;

/**
 * The abstract action is the base ActionInterface implementation, that simplifies the creation of custom actions. All core
 * action are based on this class.
 * 
 * To implement a specific action one atually only needs to implement the abstract perform() method. From within that method
 * the set_result...() methods should be called to set the action output. Everything else (registering in the action context, etc.)
 * is done automatically by the abstract action.
 * 
 * The abstract action dispatches the following events prefixed by the actions alias (@see ActionEvent):
 * - Perform (.Before/.After)
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractAction implements ActionInterface {
	private $id = null;
	private $alias = null;
	private $name = null;
	private $exface = null;
	private $app = null;
	/** @var WidgetInterface widget, that called this action */
	private $called_by_widget = null;
	/** @var ActionInterface[] contains actions, that can be performed after the current one*/
	private $followup_actions = array();
	private $result_data_sheet = null;
	private $result_message = null;
	private $result = null;
	private $performed = false;
	private $is_undoable = null;
	private $is_data_modified = null;
	
	/**
	 * @uxon
	 * @var DataSheetInterface
	 */
	private $input_data_sheet = null;
	/**
	 * @uxon template_alias Qualified alias of the template to be used to render the output of this action
	 * @var string
	 */
	private $template_alias = null;
	/**
	 * @uxon 
	 * @var unknown
	 */
	private $icon_name = null;
	/**
	 * @uxon
	 * @var integer
	 */
	private $input_rows_min = 0;
	/**
	 * @uxon
	 * @var integer
	 */
	private $input_rows_max = null;
	/**
	 * @uxon
	 * @var array
	 */
	private $disabled_behaviors = array();
	/**
	 * @uxon object_alias Qualified alias of the base meta object for this action
	 * @var string
	 */
	private $meta_object = null;
	
	function __construct(\exface\Core\CommonLogic\AbstractApp $app){
		$this->app = $app;
		$this->exface = $app->get_workbench();
		// TODO read action config from DB here
		// call init method of concrete implementation to enable some additional processing like auto install, validity checks, etc.
		$this->init();
	}
	
	protected function init(){
		
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AliasInterface::get_alias()
	 */
	public function get_alias(){
		if (is_null($this->alias)){
			$class = explode('\\', get_class($this));
			$this->alias = end($class);
		}
		return $this->alias;
	}
	
	public function set_alias($value){
		$this->alias = $value;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AliasInterface::get_alias_with_namespace()
	 */
	public function get_alias_with_namespace(){
		return $this->get_namespace() . NameResolver::NAMESPACE_SEPARATOR . $this->get_alias();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AliasInterface::get_namespace()
	 */
	public function get_namespace(){
		return $this->get_app()->get_alias_with_namespace();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_id()
	 */
	public function get_id(){
		if (is_null($this->id)){
			$this->id = md5($this->export_uxon_object()->to_json());
		}
		return $this->id;
	}
	
	public function get_app(){
		return $this->app;
	}
	
	/**
	 * Loads data from a standard UXON object (stdClass) into any action using setter functions.
	 * E.g. calls $this->set_id($source->id) for every property of the source object. Thus the behaviour of this
	 * function like error handling, input checks, etc. can easily be customized by programming good
	 * setters.
	 * @param \stdClass $source
	 */
	public function import_uxon_object(\stdClass $source){
		if (!$source) return false;
		$vars = get_object_vars($source);
		foreach ($vars as $var => $val){
			// Skip alias property if found because it was processed already to instantiate the right action class.
			// Setting the alias after instantiation is currently not possible beacuase it would mean recreating
			// the entire action.
			if ($var == 'alias') continue;
			if (method_exists($this, 'set_'.$var)){
				call_user_func(array($this, 'set_'.$var), $val);
			} else {
				throw new ActionConfigurationError($this, 'Property "' . $var . '" of action "' . $this->get_alias() . '" cannot be set: setter function not found!', '6T5DI5E');
			}
		}
		return true;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_icon_name()
	 */
	public function get_icon_name() {
		return $this->icon_name;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_icon_name()
	 */
	public function set_icon_name($value) {
		$this->icon_name = $value;
	}  
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_called_by_widget()
	 */
	public function get_called_by_widget() {
		return $this->called_by_widget;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_called_by_widget()
	 */
	public function set_called_by_widget($widget_or_widget_link) {
		if ($widget_or_widget_link instanceof WidgetInterface){
			$this->called_by_widget = $widget_or_widget_link;
		} elseif($widget_or_widget_link instanceof WidgetLink){
			$this->called_by_widget = $widget_or_widget_link->get_widget();
		} else {
			$link = WidgetLinkFactory::create_from_anything($this->exface, $widget_or_widget_link);
			$this->called_by_widget = $link->get_widget();
		}
		return $this;
	}  
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_followup_actions()
	 */
	public function get_followup_actions() {
		return $this->followup_actions;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_followup_actions()
	 */
	public function set_followup_actions(array $actions_array) {
		$this->followup_actions = $actions_array;
	}
	
	public function add_followup_action(ActionInterface $action){
		if (!$action->get_called_by_widget()){
			$action->set_called_by_widget($this->get_called_by_widget());
		}
		$this->followup_actions[] = $action;
	}
	
	/**
	 * Performs the action and registers it in the current window context.
	 * This is a wrapper function for perform() that takes care of the contexts etc. The actual logic
	 * of the action sits in the perform() method that, on the other hand should not be called
	 * from external sources because the developer of a specific action might not have taken care
	 * of contexts etc.
	 * @return ActionInterface
	 */
	private function prepare_result(){
		$this->dispatch_event('Perform.Before');
		// Register the action in the action context of the window. Since it is passed by reference, we can
		// safely do it here, befor perform(). On the other hand, this gives all kinds of action event handlers
		// the possibility to access the current action and it's current state
		$this->get_app()->get_workbench()->context()->get_scope_window()->get_action_context()->add_action($this);
		// Marke the action as performed first, to make sure it is not performed again if there is some exception
		// In the case of an exception in perform() it might be caught somewhere outside and the execution will 
		// move on an mitght lead to another call on perform()
		$this->set_performed();
		$this->perform();
		$this->dispatch_event('Perform.After');
		return $this;
	}
	
	/**
	 * Returns the resulting data sheet. Performs the action if it had not been performed yet.
	 * @return DataSheetInterface
	 */
	final public function get_result_data_sheet(){
		// Make sure, the action has been performed
		if (!$this->is_performed()){
			$this->prepare_result();
		}	
		
		if (!$this->result_data_sheet){
			// FIXME what to we do here?
		}
		return $this->result_data_sheet;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_result()
	 */
	final public function get_result(){
		// Perform the action if not yet done so
		if (!$this->is_performed()){
			$this->prepare_result();
		}
		// If the actual result is still empty, try the result data sheet - that should always be filled
		if (is_null($this->result)){
			return $this->get_result_data_sheet();
		} else {
			return $this->result;
		}
	}
	
	protected function set_result($data_sheet_or_widget_or_string){
		$this->result = $data_sheet_or_widget_or_string;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_result_stringified()
	 */
	public function get_result_stringified(){
		$result = $this->get_result();
		if ($result instanceof DataSheetInterface){
			return $result->to_uxon();
		} elseif ($result instanceof WidgetInterface){
			return '';
		} elseif (!is_object($result)){
			return $result;
		} else {
			throw new ActionOutputError($this, 'Cannot convert result object of type "' . get_class($result) . '" to string for action "' . $this->get_alias_with_namespace() . '"', '6T5DUT1');
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_result_output()
	 */
	public function get_result_output(){
		$result = $this->get_result();
		if ($result instanceof DataSheetInterface){
			return $result->to_uxon();
		} elseif ($result instanceof WidgetInterface){
			return $this->get_template()->draw($this->get_widget());
		} elseif (!is_object($result)){
			return $result;
		} else {
			throw new ActionOutputError($this, 'Cannot render output for unknown result object type "' . gettype($result) . '" of action "' . $this->get_alias_with_namespace() . '"', '6T5DUT1');
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_result_message()
	 */
	final public function get_result_message(){
		if (!$this->is_performed()){
			$this->prepare_result();
		}
		return $this->result_message;
	}
	
	protected function set_result_message($text){
		$this->result_message = $text;
		return $this;
	}
	
	protected function add_result_message($text){
		$this->result_message .= $text;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_input_data_sheet()
	 */
	public function set_input_data_sheet($data_sheet_or_uxon){
		$data_sheet = DataSheetFactory::create_from_anything($this->exface, $data_sheet_or_uxon);
		$this->input_data_sheet = $data_sheet;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_input_data_sheet()
	 */
	public function get_input_data_sheet(){
		return $this->input_data_sheet;
	}
	
	protected function set_result_data_sheet(DataSheetInterface $data_sheet){
		$this->result_data_sheet = $data_sheet;
	}
	
	/**
	 * Performs the action. Should be implemented in every action. Does not actually return anything, instead the result_data_sheet,
	 * the result message and (if needed) a separate result object should be set within the specific implementation via 
	 * set_result_data_sheet(), set_result_message() and set_result() respectively!
	 * 
	 * This method is protected because only get_result...() methods are intended to be used by external objects. In addition to performing
	 * the action they also take care of saving it to the current context, etc., while perform() ist totally depending on the specific
	 * action implementation and holds only the actual logic without all the overhead.
	 * 
	 * @return void
	 */
	abstract protected function perform();
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_input_rows_min()
	 */
	public function get_input_rows_min() {
		return $this->input_rows_min;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_input_rows_min()
	 */
	public function set_input_rows_min($value) {
		$this->input_rows_min = $value;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_input_rows_max()
	 */
	public function get_input_rows_max() {
		return $this->input_rows_max;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_input_rows_max()
	 */
	public function set_input_rows_max($value) {
		$this->input_rows_max = $value;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_meta_object()
	 */
	public function get_meta_object(){
		if (is_null($this->meta_object)){
			if ($this->get_input_data_sheet()){
				$this->meta_object = $this->get_input_data_sheet()->get_meta_object();
			} elseif ($this->get_called_by_widget()){
				$this->meta_object = $this->get_called_by_widget()->get_meta_object();
			} else {
				throw new ActionObjectNotSpecifiedError('Cannot determine the meta object, the action is performed upon! An action must either have an input data sheet or a reference to the widget, that called it, or an explicitly specified object_alias option to determine the meta object.');
			}
		}
		return $this->meta_object;
	} 
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_meta_object()
	 */
	public function set_meta_object(Object $object){
		$this->meta_object = $object;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_object_alias()
	 */
	public function set_object_alias($qualified_alias){
		if ($object = $this->get_workbench()->model()->get_object($qualified_alias)){
			$this->meta_object = $object;
		} else {
			throw new MetaObjectNotFoundError('Cannot load object "' . $qualified_alias . '" for action "' . $this->get_alias_with_namespace() . '"!', '6T5DJPP');
		}
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::implements_interface()
	 */
	public function implements_interface($interface){
		$interface = '\\exface\\Core\\Interfaces\\Actions\\' . $interface;
		if ($this instanceof $interface){
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::is_undoable()
	 */
	public function is_undoable(){
		if (is_null($this->is_undoable)){
			if ($this instanceof iCanBeUndone){
				return $this->is_undoable = true;
			} else {
				return $this->is_undoable = false;
			}
		}
		return $this->is_undoable;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_undoable()
	 */
	public function set_undoable($value){
		$this->is_undoable = $value;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::is_data_modified()
	 */
	public function is_data_modified(){
		if (is_null($this->is_data_modified)){
			if ($this instanceof iModifyData){
				return $this->is_data_modified = true;
			} else {
				return $this->is_data_modified = false;
			}
		}
		return $this->is_data_modified;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_data_modified()
	 */
	public function set_data_modified($value){
		$this->is_data_modified = $value;
		return $this;
	}
	
	public function get_undo_action(){
		if ($this->is_undoable()){
			return ActionFactory::create_from_string($this->exface, 'exface.Core.UndoAction', $this->get_called_by_widget());
		}
	}
	
	/**
	 * Returns a loadable UXON-representation of the action
	 * @return UxonObject
	 */
	public function export_uxon_object(){
		$uxon = $this->get_workbench()->create_uxon_object();
		$uxon->alias = $this->get_alias_with_namespace();
		if ($this->get_called_by_widget()){
			$uxon->called_by_widget = $this->get_called_by_widget()->create_widget_link()->export_uxon_object();
		}
		$uxon->template_alias = $this->get_template_alias();
		$uxon->input_data_sheet = $this->get_input_data_sheet()->export_uxon_object();
		$uxon->disabled_behaviors = UxonObject::from_array($this->get_disabled_behaviors());
		return $uxon;
	}
	
	protected function dispatch_event($event_name){
		/* @var $event \exface\Core\Events\ActionEvent */
		$this->get_app()->get_workbench()->event_manager()->dispatch(EventFactory::create_action_event($this, $event_name));
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::exface()
	 * @return Workbench
	 */
	public function get_workbench(){
		return $this->exface;
	}
	
	protected final function is_performed(){
		return $this->performed;
	}
	
	protected final function set_performed(){
		$this->performed = true;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_disabled_behaviors()
	 */
	public function set_disabled_behaviors(array $behavior_aliases){
		$this->disabled_behaviors = $behavior_aliases;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_disabled_behaviors()
	 */
	public function get_disabled_behaviors(){
		return $this->disabled_behaviors;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_called_on_ui_page()
	 */
	public function get_called_on_ui_page(){
		return $this->get_called_by_widget() ? $this->get_called_by_widget()->get_page() : $this->get_workbench()->ui()->get_page_current();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_template()
	 */
	public function get_template() {
		return $this->get_workbench()->ui()->get_template($this->get_template_alias());
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_template_alias()
	 */
	public function get_template_alias() {
		if (is_null($this->template_alias)){
			$this->template_alias = $this->exface->ui()->get_template_from_request()->get_alias_with_namespace();
		}
		return $this->template_alias;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_template_alias()
	 */
	public function set_template_alias($value) {
		$this->template_alias = $value;
		return $this;
	} 
	
	/**
	 * Returns the translation string for the given message id. 
	 * 
	 * This is a shortcut for calling $this->get_app()->get_translator()->translate(). Additionally it will automatically append an
	 * action prefix to the given id: e.g. $action->translate('SOME_MESSAGE') will result in 
	 * $action->get_app()->get_translator()->translate('ACTION.ALIAS.SOME_MESSAGE')
	 * 
	 * @see Translation::translate()
	 * @see Translation::translate_plural()
	 * 
	 * @param string $message_id
	 * @param array $placeholders
	 * @param float $number_for_plurification
	 * @return string
	 */
	public function translate($message_id, array $placeholders = null, $number_for_plurification = null){
		$message_id = trim($message_id);
		$key_prefix = 'ACTION.' . mb_strtoupper($this->get_alias()) . '.';
		if (mb_strpos($message_id, $key_prefix) !== 0){
			$message_id = $key_prefix . $message_id;
		}
		if (!is_null($number_for_plurification)){
			return $this->get_app()->get_translator()->translate_plural($message_id, $number_for_plurification, $placeholders);
		} else {
			return $this->get_app()->get_translator()->translate($message_id, $placeholders);
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::get_name()
	 */
	public function get_name(){
		if (is_null($this->name)){
			$this->name = $this->translate('NAME');
		}
		return $this->name;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Actions\ActionInterface::set_name()
	 */
	public function set_name($value){
		$this->name = $value;
		return $this;
	}
	
	public function copy(){
		return clone $this;
	}
}
?>