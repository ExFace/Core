<?php namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\AbstractBehavior;
use exface\Core\Events\WidgetEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\StateMachineConfigError;

/**
 * 
 * @author SFL
 *
 */
class StateMachineBehavior extends AbstractBehavior {
	
	private $state_attribute_alias = null;
	private $states = null;
	
	private $smstates = null;
	
	const DEFAULT_STATE = 10;
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractBehavior::register()
	 */
	public function register(){
		$this->get_workbench()->event_manager()->add_listener($this->get_object()->get_alias_with_namespace() . '.Widget.Prefill.After', array($this, 'set_widget_states'));
		$this->set_registered(true);
	}
	
	/**
	 * 
	 * @return unknown
	 */
	public function get_state_attribute_alias() {
		return $this->state_attribute_alias;
	}
	
	/**
	 * 
	 * @param unknown $value
	 * @return \exface\Core\Behaviors\StateMachineBehavior
	 */
	public function set_state_attribute_alias($value) {
		$this->state_attribute_alias = $value;
		return $this;
	}
	
	/**
	 * 
	 * @return \exface\Core\CommonLogic\UxonObject
	 */
	public function get_states() {
		return $this->states;
	}
	
	/**
	 * 
	 * @param unknown $value
	 * @throws StateMachineConfigError
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
					throw new StateMachineConfigError('Property "' . $var . '" of StateMachineState cannot be set: setter function not found!');
				}
			}
			$this->smstates[$state] = $smstate;
		}
		return $this;
	}
	
	/**
	 * 
	 */
	public function get_smstates() {
		return $this->smstates;
	}
	
	/**
	 * 
	 * @param unknown $state_id
	 * @return mixed
	 */
	public function get_smstate($state_id) {
		return $this->smstates[$state_id];
	}
	
	/**
	 * 
	 * @param unknown $state_id
	 * @return unknown
	 */
	public function get_state_buttons($state_id) {
		if ($this->is_disabled() || !$this->get_smstates()) return [];
		$smstate = $this->get_smstate($state_id);
		if (!$smstate) { $smstate = $this->get_smstate($this::DEFAULT_STATE); }
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
		$uxon->set_property('states', $this->get_states());
		return $uxon;
	}
	
	/**
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
			$current_state = DEFAULT_STATE;
		}
		
		if (method_exists($widget, 'get_attribute_alias')
				&& ($disabled_attributes = $this->get_smstate($current_state)->get_disabled_attributes())
				&& in_array($widget->get_attribute_alias(), $disabled_attributes)) {
			$widget->set_disabled(true);
		}
	}
}

?>