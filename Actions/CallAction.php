<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iRunTemplateScript;
use exface\Core\Exceptions\Actions\ActionInputError;

/**
 * This action performs another action specified in the action_alias property or via request parameter "call=your_action_alias".
 * 
 * This action behaves exactly as the action to be called, but offers a universal interface for multiple action types. Thus, if you
 * need a custom server call somewhere in a template, but you do not know, which action will be called in advance, you can request
 * this action an pass the actually desired one as a request parameter.
 * 
 * @author Andrej Kabachnik
 *
 */
class CallAction extends AbstractAction {
	private $action = null;
	private $action_alias = null;
	
	protected function perform(){
		$this->set_result($this->get_action()->get_result());
		$this->set_result_message($this->get_action()->get_result_message());
		if ($parent_result = $this->get_action()->get_result_data_sheet()){
			$this->set_result_data_sheet($parent_result);
		}
	}
	
	/**
	 * 
	 * @return \exface\Core\Interfaces\Actions\ActionInterface
	 */
	public function get_action() {
		if (is_null($this->action)){
			$action = ActionFactory::create_from_string($this->get_workbench(), $this->get_action_alias(), $this->get_called_by_widget());
			$this->validate_action($action);
			$this->action = $action;
		}
		return $this->action;
	}
	
	protected function validate_action(ActionInterface $action){
		if ($action instanceof iRunTemplateScript){
			throw new ActionInputError($this, 'Cannot call actions running template scripts for object baskets! Attempted to call "' . $action->get_alias_with_namespace() . '".');
		}
		// Add other checks
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractAction::implements_interface()
	 */
	public function implements_interface($string){
		return $this->get_action()->implements_interface($string);	
	}
	
	public function get_result_output(){
		return $this->get_action()->get_result_output();
	}
	
	public function get_result_stringified(){
		return $this->get_action()->get_result_stringified();
	}
	
	public function is_data_modified(){
		return $this->get_action()->is_data_modified();
	}
	
	public function is_undoable(){
		// TODO make action wrapper undoable if wrapped action is undoable!
		return false;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractAction::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = parent::export_uxon_object();
		$uxon->set_property('action_alias', $this->get_action_alias());
		return $uxon;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_action_alias() {
		if (is_null($this->action_alias)){
			$this->action_alias = $this->get_workbench()->get_request_param('call');
		}
		return $this->action_alias;
	}
	
	/**
	 * 
	 * @param string $value
	 * @return \exface\Core\Actions\ObjectBasketCallAction
	 */
	public function set_action_alias($value) {
		$this->action_alias = $value;
		$this->action = null;
		return $this;
	}  
	
	public function has_property($name){
		if (parent::has_property($name)){
			return true;
		} elseif ($this->get_action() && $this->get_action()->has_property($name)){
			return true;
		}
		return false;
	}
	
	/**
	 * 
	 * @param string $method
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call($method, $arguments){
		return call_user_func_array(array($this->get_action(), $method), $arguments);
	}
	
	public function set_input_data_sheet($data_sheet_or_uxon){
		return $this->get_action()->set_input_data_sheet($data_sheet_or_uxon);
	}
	
	public function get_input_data_sheet(){
		return $this->get_action()->get_input_data_sheet();
	}
	
	public function get_input_rows_max() {
		return $this->get_action()->get_input_rows_max();
	}
	
	public function set_input_rows_max($value) {
		$this->get_action()->set_input_rows_max($value);
		return $this;
	}
	
	public function get_input_rows_min() {
		return $this->get_action()->get_input_rows_min();
	}
	
	public function set_input_rows_min($value) {
		$this->get_action()->set_input_rows_min($value);
		return $this;
	}
	
	  
}