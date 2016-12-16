<?php namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\AbstractBehavior;
use exface\Core\Events\WidgetEvent;
use exface\Core\Widgets\Dialog;
use exface\Core\Widgets\Container;

class StateMachineBehavior extends AbstractBehavior {
	
	private $state_attribute_alias = null;
	private $states = null;
	
	const DEFAULT_STATE = 10;
	
	public function register(){
		$this->get_workbench()->event_manager()->add_listener($this->get_object()->get_alias_with_namespace() . '.Widget.Prefill', array($this, 'set_widget_states'));
		$this->set_registered(true);
	}
	
	public function get_state_attribute_alias() {
		return $this->state_attribute_alias;
	}
	
	public function set_state_attribute_alias($value) {
		$this->state_attribute_alias = $value;
		return $this;
	}
	
	public function get_states() {
		return $this->states;
	}
	
	public function set_states($value) {
		$this->states = $value;
		return $this;
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
	
	public function set_widget_states(WidgetEvent $event) {
		if ($this->is_disabled()) return;
		if (empty($this->state_attribute_alias) || empty($this->states)) return;
		
		$widget = $event->get_widget();
		
		// Do not do anything, if the base object of the widget is not the object with the behavior and is not
		// extended from it.
		if (!$widget->get_meta_object()->is_exactly($this->get_object())) return;
		
		if (!empty($prefill_data = $widget->get_prefill_data()) &&
				!empty($state_column = $prefill_data->get_column_values($this->state_attribute_alias))) {
			$current_state = $state_column[0];
		} else {
			$current_state = DEFAULT_STATE;
		}
		
		if (method_exists($widget, 'get_attribute_alias') && !empty($this->states->$current_state->disabled_attributes)) {
			$disabled_attributes = array_map('trim', explode(',', $this->states->$current_state->disabled_attributes));
			if (in_array($widget->get_attribute_alias(), $disabled_attributes)) {
				$widget->set_disabled(true);
			}
		}
		
		//if ($widget instanceof Dialog && !empty($this->states->$current_state->buttons)) {
		//	$widget->set_buttons($this->states->$current_state->buttons);
		//}
	}
}

?>