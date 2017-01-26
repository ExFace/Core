<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iRunTemplateScript;
use exface\Core\Exceptions\Actions\ActionInputError;

class ObjectBasketCallAction extends AbstractAction {
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
			$action->set_input_data_sheet($this->get_input_data_sheet());
			$action->set_template_alias($this->get_template_alias());
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
			$this->action_alias = $this->get_workbench()->get_request_param('basketAction');
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
	
}