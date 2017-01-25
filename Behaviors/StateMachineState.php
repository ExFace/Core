<?php namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\UxonObject;

/**
 * Defines a state for the StateMachineBehavior.
 * 
 * @author SFL
 */
class StateMachineState {
	
	private $state_id = null;
	private $buttons = [];
	private $disabled_attributes_aliases = [];
	private $transitions = [];
	
	/**
	 * Returns the state id.
	 * 
	 * @return integer|string
	 */
	public function get_state_id() {
		return $this->state_id;
	}
	
	/**
	 * Defines the state id.
	 * 
	 * @param integer|string $value
	 * @return \exface\Core\Behaviors\StateMachineState
	 */
	public function set_state_id($value) {
		$this->state_id = $value;
		return $this;
	}
	
	/**
	 * Returns the buttons for the state.
	 * 
	 * @return UxonObject
	 */
	public function get_buttons() {
		return $this->buttons;
	}
	
	/**
	 * Defines the buttons for the state.
	 * 
	 * Example:
	 *        {
	 *	        "20": {
	 *	          "caption": "20 Annahme best�tigen",
	 *	          "action": {
	 *	            "alias": "exface.Core.UpdateData",
	 *	            "input_data_sheet": {
	 *	              "object_alias": "alexa.RMS.CUSTOMER_COMPLAINT",
	 *	              "columns": [
	 *	                {
	 *	                  "attribute_alias": "STATE_ID",
	 *	                  "formula": "=NumberValue('20')"
	 *	                },
	 *	                {
	 *	                  "attribute_alias": "TS_UPDATE"
	 *	                }
	 *	              ]
	 *	            }
	 *	          }
	 *	        }
	 *	      }
	 * 
	 * @param UxonObject $value
	 * @return \exface\Core\Behaviors\StateMachineState
	 */
	public function set_buttons($value) {
		$this->buttons = $value;
		return $this;
	}
	
	/**
	 * Defines the disabled attributes aliases for the state.
	 * 
	 * Example:
	 *        [
	 *	        "COMPLAINT_NO"
	 *	      ]
	 * 
	 * @param string[] $value
	 * @return \exface\Core\Behaviors\StateMachineState
	 */
	public function set_disabled_attributes_aliases($value) {
		$this->disabled_attributes_aliases = $value;
		return $this;
	}
	
	/**
	 * Returns the disabled attributes aliases for the state.
	 * 
	 * @return string[]
	 */
	public function get_disabled_attributes_aliases() {
		return $this->disabled_attributes_aliases;
	}
	
	/**
	 * Returns the allowed transitions for the state.
	 * 
	 * @return integer[]
	 */
	public function get_transitions() {
		return $this->transitions;
	}
	
	/**
	 * Defines the allowed transitions for the state.
	 * 
	 * Example:
	 *        [
	 *	        10,
	 *	        20,
	 *	        30,
	 *	        50,
	 *	        60,
	 *	        70,
	 *	        90,
	 *	        99
	 *	      ]
	 * 
	 * @param integer[] $value
	 * @return \exface\Core\Behaviors\StateMachineState
	 */
	public function set_transitions($value) {
		$this->transitions = $value;
		return $this;
	}
}
?>
