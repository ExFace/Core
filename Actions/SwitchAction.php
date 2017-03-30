<?php
namespace exface\Core\Actions;

use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionRuntimeError;

/**
 * This action performs calls one of the actions specified in the switch_action_map property depending on 
 * the first value of the switch_attribute_alias column in the input data sheet.
 * 
 * TODO It seems, that switching actions makes lot's of problems if these actions implements different interfaces.
 * It's not really SwitchAction, but rather SwitchActionConfig - maybe we can attach that kind of switcher-logic
 * to all actions? Maybe this will be an easy-to-built extension for the planned DataSheetMapper?
 * 
 * @author Andrej Kabachnik
 *
 */
class SwitchAction extends ActionChain {
	private $switch_attribute_alias = null;
	private $switch_action_map = null;
	
	protected function perform(){
		if (!$this->get_input_data_sheet() || !$this->get_input_data_sheet()->get_columns()->get_by_expression($this->get_switch_attribute_alias())){
			throw new ActionInputMissingError($this, 'Cannot perform SwitchAction: Missing column "' . $this->get_switch_attribute_alias() . '" in input data!');
		}
		
		$switch_value = $this->get_input_data_sheet()->get_columns()->get_by_expression($this->get_switch_attribute_alias())->get_cell_value(0);
		if ($action = $this->get_actions_array()[$this->get_switch_action_map()->get_property($switch_value)]){
			$this->get_actions()->remove_all()->add($action);
		} else {
			throw new ActionRuntimeError($this, 'No action found to switch to for value "' . $switch_value . '" of "' . $this->get_switch_attribute_alias() . '"!');
		}
		return parent::perform();
	}
	
	protected function get_actions_array(){
		return array_values($this->get_actions()->get_all());
	}
	 
	public function get_switch_attribute_alias() {
		return $this->switch_attribute_alias;
	}
	
	public function set_switch_attribute_alias($value) {
		$this->switch_attribute_alias = $value;
		return $this;
	}  
	
	public function get_switch_action_map() {
		return $this->switch_action_map;
	}
	
	public function set_switch_action_map($value) {
		$this->switch_action_map = $value;
		return $this;
	}
	
	public function implements_interface($interface){
		if ($this->is_performed()){
			return $this->get_actions()->get_first()->implements_interface($interface);
		} else {
			return parent::implements_interface($interface);
		}
	}
	  
}