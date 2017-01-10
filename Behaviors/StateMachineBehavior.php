<?php namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\AbstractBehavior;
use exface\Core\Events\WidgetEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Events\DataSheetEvent;
use exface\Core\Exceptions\Behaviors\StateMachineUpdateException;
use exface\Core\Factories\DataSheetFactory;

/**
 * A behavior that defines states and transitions between these states for an objects.
 * 
 * @author SFL
 */
class StateMachineBehavior extends AbstractBehavior {
	
	private $state_attribute_alias = null;
	private $default_state = null;
	private $states = null;
	private $smstates = null;
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractBehavior::register()
	 */
	public function register(){
		$this->get_workbench()->event_manager()->add_listener($this->get_object()->get_alias_with_namespace() . '.Widget.Prefill.After', array($this, 'set_widget_states'));
		$this->get_workbench()->event_manager()->add_listener($this->get_object()->get_alias_with_namespace() . '.DataSheet.UpdateData.Before', array($this, 'check_for_conflicts_on_update'));
		$this->set_registered(true);
	}
	
	/**
	 * Returns the state attribute alias.
	 * 
	 * @return string
	 */
	public function get_state_attribute_alias() {
		return $this->state_attribute_alias;
	}
	
	/**
	 * Defines the attribute alias, that holds the state id.
	 * 
	 * @uxon-property state_attribute_alias
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return \exface\Core\Behaviors\StateMachineBehavior
	 */
	public function set_state_attribute_alias($value) {
		$this->state_attribute_alias = $value;
		return $this;
	}
	
	/**
	 * Determines the state attribute from the alias and the attached object and
	 * returns it.
	 * 
	 * @return \exface\Core\CommonLogic\Model\Attribute
	 */
	public function get_state_attribute() {
		return $this->get_object()->get_attribute($this->get_state_attribute_alias());
	}
		
	/**
	 * Returns the default state.
	 * 
	 * @return unknown
	 */
	public function get_default_state() {
		if (is_null($this->default_state)) {
			throw new BehaviorConfigurationError('Property default_state of StateMachineBehavior is undefined!');
		}
		return $this->default_state;
	}
	
	/**
	 * Defines the default state, which is used if no object state can be determined
	 * (e.g. to determine possible values for the StateMenuButton).
	 * 
	 * @uxon-property default_state
	 * @uxon-type number
	 * 
	 * @param integer $value
	 * @return \exface\Core\Behaviors\StateMachineBehavior
	 */
	public function set_default_state($value) {
		$this->default_state = $value;
		return $this;
	}
	
	/**
	 * Returns the states of the state machine.
	 * 
	 * @return \exface\Core\CommonLogic\UxonObject
	 */
	public function get_states() {
		return $this->states;
	}
	
	/**
	 * Defines the states of the state machine.
	 * 
	 * The states are set by a JSON object or array with state ids for keys and an objects describing the state for values.
	 * 
	 * Example:
	 * "states": {
	 *	    "10": {
	 *	      "buttons": [
	 *	        {
	 *	          "caption": "20 Annahme bestätigen",
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
	 *	      ],
	 *	      "disabled_attributes": [
	 *	        "COMPLAINT_NO"
	 *	      ],
	 *	      "transitions": [
	 *	        10,
	 *	        20,
	 *	        30,
	 *	        50,
	 *	        60,
	 *	        70,
	 *	        90,
	 *	        99
	 *	      ]
	 *	    }
	 * }
	 * 
	 * @uxon-property states
	 * @uxon-type object
	 * 
	 * @param unknown $value
	 * @throws BehaviorConfigurationError
	 * @return \exface\Core\Behaviors\StateMachineBehavior
	 */
	public function set_states($value) {
		$this->states = UxonObject::from_anything($value);
		$this->smstates = [];
		$states = get_object_vars($this->states);
		foreach ($states as $state => $uxon_smstate){
			$smstate = new StateMachineState();
			$smstate->set_state_id($state);
			foreach ($uxon_smstate as $var => $val) {
				if (method_exists($smstate, 'set_'.$var)){
					call_user_func(array($smstate, 'set_'.$var), $val);
				} else {
					throw new BehaviorConfigurationError('Property "' . $var . '" of StateMachineState cannot be set: setter function not found!');
				}
			}
			$this->smstates[$state] = $smstate;
		}
		return $this;
	}
	
	/**
	 * Returns an array of StateMachineState objects.
	 * 
	 * @return array of StateMachineState
	 */
	public function get_smstates() {
		return $this->smstates;
	}
	
	/**
	 * Returns the StateMachineState object belonging to the passed state id.
	 * 
	 * @param integer $state_id
	 * @return StateMachineState
	 */
	public function get_smstate($state_id) {
		return $this->smstates[$state_id];
	}
	
	/**
	 * Returns an array of buttons belonging to the StateMachineState with the
	 * passed state id.
	 * 
	 * @param integer $state_id
	 * @return array of UxonObject
	 */
	public function get_state_buttons($state_id) {
		if ($this->is_disabled() || !$this->get_smstates()) return [];
		$smstate = $this->get_smstate($state_id);
		if (!$smstate) { $smstate = $this->get_smstate($this->get_default_state()); }
		return $smstate instanceof StateMachineState ? $smstate->get_buttons() : [];
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractBehavior::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = parent::export_uxon_object();
		$uxon->set_property('state_attribute_alias', $this->get_state_attribute_alias());
		$uxon->set_property('default_state', $this->get_default_state());
		$uxon->set_property('states', $this->get_states());
		return $uxon;
	}
	
	/**
	 * This method is called when a widget belonging to an object with this event
	 * attached is being prefilled. It is checked if this widget belongs to a dis-
	 * abled attribute. If so the widget gets also disabled.
	 * 
	 * @param WidgetEvent $event
	 */
	public function set_widget_states(WidgetEvent $event) {
		if ($this->is_disabled()) return;
		if (!$this->get_state_attribute_alias() || !$this->get_states()) return;
		
		$widget = $event->get_widget();
		
		// Do not do anything, if the base object of the widget is not the object with the behavior and is not
		// extended from it.
		if (!$widget->get_meta_object()->is($this->get_object())) return;
		
		if (($prefill_data = $widget->get_prefill_data()) &&
				($state_column = $prefill_data->get_column_values($this->get_state_attribute_alias()))) {
			$current_state = $state_column[0];
		} else {
			$current_state = $this->get_default_state();
		}
		
		if (method_exists($widget, 'get_attribute_alias')
				&& ($disabled_attributes = $this->get_smstate($current_state)->get_disabled_attributes())
				&& in_array($widget->get_attribute_alias(), $disabled_attributes)) {
			$widget->set_disabled(true);
		}
	}
	
	/**
	 * This method is called when an object with this event attached is being updated.
	 * Here it is checked the object changes the state and if so if the state-transition
	 * is allowed. It is also checked if attributes, which are disabled at the current
	 * state are changed. If a disallowed behavior is detected an error is thrown.
	 * 
	 * @param DataSheetEvent $event
	 * @throws StateMachineUpdateException
	 */
	public function check_for_conflicts_on_update(DataSheetEvent $event) {
		if ($this->is_disabled()) return;
		if (!$this->get_state_attribute_alias() || !$this->get_states()) return;
		
		$data_sheet = $event->get_data_sheet();
		
		// Do not do anything, if the base object of the widget is not the object with the behavior and is not
		// extended from it.
		if (!$data_sheet->get_meta_object()->is($this->get_object())) return;
		
		// Read the unchanged object from the database
		$check_sheet = DataSheetFactory::create_from_object($this->get_object());
		foreach ($this->get_object()->get_attributes() as $attr) {
			$check_sheet->get_columns()->add_from_attribute($attr);
		}
		$check_sheet->add_filter_from_column_values($data_sheet->get_uid_column());
		$check_sheet->data_read();
		$check_column = $check_sheet->get_columns()->get_by_attribute($this->get_state_attribute());
		$check_nr = count($check_column->get_values());
		
		// Check all the updated attributes for disabled attributes, if a disabled attribute
		// is changed throw an error
		foreach ($check_column->get_values() as $row_nr => $check_val) {
			$disabled_attributes = $this->get_smstate($check_val)->get_disabled_attributes();
			foreach ($data_sheet->get_columns() as $col) {
				if (in_array($col->get_attribute_alias(), $disabled_attributes)) {
					$updated_val = $col->get_cell_value($data_sheet->get_uid_column()->find_row_by_value($check_sheet->get_uid_column()->get_cell_value($row_nr)));
					$check_val = $check_sheet->get_cell_value($col->get_attribute_alias(), $row_nr);
					if ($updated_val != $check_val) {
						$data_sheet->data_mark_invalid();
						throw new StateMachineUpdateException($data_sheet, 'Cannot update data in data sheet with "' . $data_sheet->get_meta_object()->get_alias_with_namespace() . '": attribute '.$col->get_attribute_alias().' is disabled in the current state ('.$check_val.')!');
					}
				}
			}
		}
		
		// Check if the state column is present in the sheet, if so get the old value and check
		// if the transition is allowed, throw an error if not
		if ($updated_column = $data_sheet->get_columns()->get_by_attribute($this->get_state_attribute())) {
			$update_nr = count($updated_column->get_values());
			
			if ($check_nr == $update_nr) {
				//beim Bearbeiten eines einzelnen Objektes ueber einfaches Bearbeiten, Massenupdate in Tabelle, Massenupdate
				//	ueber Knopf $check_nr == 1, $update_nr == 1
				//beim Bearbeiten mehrerer Objekte ueber Massenupdate in Tabelle $check_nr == $update_nr > 1
				foreach ($updated_column->get_values() as $row_nr => $updated_val) {
					$check_val = $check_column->get_cell_value($check_sheet->get_uid_column()->find_row_by_value($data_sheet->get_uid_column()->get_cell_value($row_nr)));
					$allowed_transitions = $this->get_smstate($check_val)->get_transitions();
					if (!in_array($updated_val, $allowed_transitions)) {
						$data_sheet->data_mark_invalid();
						throw new StateMachineUpdateException($data_sheet, 'Cannot update data in data sheet with "' . $data_sheet->get_meta_object()->get_alias_with_namespace() . '": state transition from '.$check_val.' to '.$updated_val.' is not allowed!');
					}
				}
				
			} else if ($check_nr > 1 && $update_nr == 1) {
				//beim Bearbeiten mehrerer Objekte ueber Massenupdate ueber Knopf, Massenupdate ueber Knopf mit Filtern
				//	$check_nr > 1, $update_nr == 1
				$updated_val = $updated_column->get_values()[0];
				foreach ($check_column->get_values() as $row_nr => $check_val) {
					$allowed_transitions = $this->get_smstate($check_val)->get_transitions();
					if (!in_array($updated_val, $allowed_transitions)) {
						$data_sheet->data_mark_invalid();
						throw new StateMachineUpdateException($data_sheet, 'Cannot update data in data sheet with "' . $data_sheet->get_meta_object()->get_alias_with_namespace() . '": state transition from '.$check_val.' to '.$updated_val.' is not allowed!');
					}
				}
			}
		}
	}
}

?>