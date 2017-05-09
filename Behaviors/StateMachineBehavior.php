<?php namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\AbstractBehavior;
use exface\Core\Events\WidgetEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Events\DataSheetEvent;
use exface\Core\Exceptions\Behaviors\StateMachineUpdateException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Exceptions\UxonMapError;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;

/**
 * A behavior that defines states and transitions between these states for an objects.
 * 
 * @author SFL
 */
class StateMachineBehavior extends AbstractBehavior {
	
	private $state_attribute_alias = null;
	private $default_state = null;
	private $uxon_states = null;
	private $states = null;
    private $progress_bar_color_map = null;

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
	 * @throws BehaviorConfigurationError
	 * @return string
	 */
	public function get_state_attribute_alias() {
		if (is_null($this->state_attribute_alias)){
			throw new BehaviorConfigurationError($this->get_object(), 'Cannot initialize StateMachineBehavior for "' . $this->get_object()->get_alias_with_namespace() . '": state_attribute_alias not set in behavior configuration!', '6TG2ZFI');
		}
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
	 * @return StateMachineState
	 */
	public function get_default_state() {
		return $this->get_state($this->get_default_state_id());
	}
	
	/**
	 * Defines the default state id, which is to be used if no object state can be determined
	 * (e.g. to determine possible values for the StateMenuButton).
	 * 
	 * @uxon-property default_state
	 * @uxon-type number
	 * 
	 * @param integer|string|StateMachineState $value
	 * @return \exface\Core\Behaviors\StateMachineBehavior
	 */
	public function set_default_state($value) {
		if ($value instanceof StateMachineState) {
			if (!array_key_exists($value->get_state_id(), $this->get_states())) {
				$this->add_state($value);
			}
			$this->default_state = $value->get_state_id();
		} elseif (is_int($value) || is_string($value)) {
			$this->default_state = $value;
		} else {
			throw new BehaviorConfigurationError($this->get_object(), 'Can not set default state for "' . $this->get_object()->get_alias_with_namespace() . '": the argument passed to set_default_state() is neither a StateMachineState nor an integer nor a string!');
		}
		
		return $this;
	}
	
	/**
	 * Returns the default state id.
	 *
	 * @throws BehaviorConfigurationError
	 * @return integer|string
	 */
	public function get_default_state_id() {
		if (is_null($this->default_state)) {
			if (count($states = $this->get_states()) > 0){
				$this->default_state = reset($states)->get_state_id();
			} else {
				throw new BehaviorConfigurationError($this->get_object(), 'The default state cannot be determined for "' . $this->get_object()->get_alias_with_namespace() . '": neither state definitions nor a default state are set!', '6TG2ZFI');
			}
		}
		return $this->default_state;
	}
	
	/**
	 * Returns an array of StateMachineState objects.
	 *
	 * @return StateMachineState[]
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
	 * 	"states": {
	 *	    "10": {
	 *	      "buttons": {
	 *	        "10": {
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
	 *	      },
	 *	      "disabled_attributes_aliases": [
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
	 * 	}
	 * 
	 * @uxon-property states
	 * @uxon-type object
	 * 
	 * @param UxonObject|StateMachineState[] $value
	 * @throws BehaviorConfigurationError
	 * @return \exface\Core\Behaviors\StateMachineBehavior
	 */
	public function set_states($value) {
		$this->uxon_states = UxonObject::from_anything($value);
		
		if ($value instanceof UxonObject) {
			$this->states = [];
			$states = get_object_vars($this->uxon_states);
			foreach ($states as $state => $uxon_smstate){
				$smstate = new StateMachineState();
				$smstate->set_state_id($state);
				if ($uxon_smstate){
					try {
						$uxon_smstate->map_to_class_setters($smstate);
					} catch (UxonMapError $e){
						throw new BehaviorConfigurationError($this->get_object(), 'Cannot load UXON configuration for state machine state. ' . $e->getMessage(), null, $e);
					}
				}
				$this->add_state($smstate);
			}
		} elseif (is_array($value)) {
			$this->states = $value;
		} else {
			throw new BehaviorConfigurationError($this->get_object(), 'Can not set states for "' . $this->get_object()->get_alias_with_namespace() . '": the argument passed to set_states() is neither an UxonObject nor an array!');
		}
		
		return $this;
	}
	
	/**
	 * Returns the StateMachineState object belonging to the passed state id.
	 * 
	 * @param integer|string $state_id
	 * @return StateMachineState
	 */
	public function get_state($state_id) {
		return $this->states[$state_id];
	}
	
	/**
	 * Adds a StateMachineState to the Behavior.
	 * 
	 * @param StateMachineState $state
	 */
	public function add_state($state) {
		$this->states[$state->get_state_id()] = $state;
	}
	
	/**
	 * Returns the states of the state machine.
	 *
	 * @return \exface\Core\CommonLogic\UxonObject
	 */
	public function get_uxon_states() {
		return $this->uxon_states;
	}
	
	/**
	 * Returns an array of buttons belonging to the StateMachineState with the
	 * passed state id.
	 * 
	 * @param integer|string $state_id
	 * @return UxonObject[]
	 */
	public function get_state_buttons($state_id) {
		if ($this->is_disabled() || !$this->get_states()) return [];
		$smstate = $this->get_state($state_id);
		if (!$smstate) { $smstate = $this->get_default_state(); }
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
		$uxon->set_property('default_state', $this->get_default_state_id());
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
		
		if (!($prefill_data = $widget->get_prefill_data()) ||
				!($prefill_data->get_uid_column()) ||
				!($state_column = $prefill_data->get_column_values($this->get_state_attribute_alias())) ||
				!($current_state = $state_column[0])) {
			$current_state = $this->get_default_state_id();
		}
		
		// Throw an error if the current state is not in the state machine definition!
		if ($current_state && !$this->get_state($current_state)){
			throw new BehaviorRuntimeError($this->get_object(), 'Cannot disable widget of uneditable attributes for state "' . $current_state . '": State not found in the the state machine behavior definition!', '6UMF9UL');
		}
		
		if (($widget instanceof iShowSingleAttribute)
				&& ($disabled_attributes = $this->get_state($current_state)->get_disabled_attributes_aliases())
				&& in_array($widget->get_attribute_alias(), $disabled_attributes)) {
			// set_readonly() statt set_disabled(), dadurch werden die deaktivierten
			// Widgets nicht gespeichert. Behebt einen Fehler, der dadurch ausgeloest
			// wurde, dass ein deaktiviertes Widget durch einen Link geaendert wurde,
			// und sich der Wert dadurch vom Wert in der DB unterschied ->
			// StateMachineUpdateException
			$widget->set_readonly(true);
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
		foreach ($data_sheet->get_rows() as $updated_row_nr => $updated_row) {
			$check_row_nr = $check_sheet->get_uid_column()->find_row_by_value($data_sheet->get_uid_column()->get_cell_value($updated_row_nr));
			$check_state_val = $check_column->get_cell_value($check_row_nr);
			$disabled_attributes = $this->get_state($check_state_val)->get_disabled_attributes_aliases();
			foreach ($updated_row as $attribute_alias => $updated_val) {
				if (in_array($attribute_alias, $disabled_attributes)) {
					$check_val = $check_sheet->get_cell_value($attribute_alias, $check_row_nr);
					if ($updated_val != $check_val) {
						$data_sheet->data_mark_invalid();
						throw new StateMachineUpdateException($data_sheet, 'Cannot update data in data sheet with "' . $data_sheet->get_meta_object()->get_alias_with_namespace() . '": attribute ' . $attribute_alias . ' is disabled in the current state (' . $check_state_val . ')!');
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
					$allowed_transitions = $this->get_state($check_val)->get_transitions();
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
					$allowed_transitions = $this->get_state($check_val)->get_transitions();
					if (!in_array($updated_val, $allowed_transitions)) {
						$data_sheet->data_mark_invalid();
						throw new StateMachineUpdateException($data_sheet, 'Cannot update data in data sheet with "' . $data_sheet->get_meta_object()->get_alias_with_namespace() . '": state transition from '.$check_val.' to '.$updated_val.' is not allowed!');
					}
				}
			}
		}
	}

    /**
     * Sets color map for use in for instance ProgressBar formula.
     *
     * @param array $progress_bar_color_map
     */
	public function set_progress_bar_color_map($progress_bar_color_map)
    {
        $uxonColorMap = UxonObject::from_anything($progress_bar_color_map);
        if ($uxonColorMap instanceof UxonObject) {
            $colorMap = array();
            foreach ($uxonColorMap as $progressBarValue => $color){
                $colorMap[$progressBarValue] = $color;
            }
            $this->progress_bar_color_map = $colorMap;
        } elseif (is_array($progress_bar_color_map)) {
            $this->progress_bar_color_map = $progress_bar_color_map;
        } else {
            throw new BehaviorConfigurationError($this->get_object(), 'Can not set progress_bar_color_map for "' . $this->get_object()->get_alias_with_namespace() . '": the argument passed to set_progress_bar_color_map() is neither an UxonObject nor an array!');
        }
    }

    /**
     * Returns color map for use in for instance ProgressBar formula.
     *
     * @return array
     */
    public function get_progress_bar_color_map()
    {
        return $this->progress_bar_color_map;
    }
}

?>