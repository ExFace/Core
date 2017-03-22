<?php
namespace exface\Core\Actions;

use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\CommonLogic\UxonObject;

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