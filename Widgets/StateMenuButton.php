<?php
namespace exface\Core\Widgets;
use exface\Core\Behaviors\StateMachineState;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\MetaModelBehaviorException;

class StateMenuButton extends MenuButton {
	
	private $smb_buttons_set = false;
	private $show_states = [];
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\MenuButton::get_buttons()
	 */
	public function get_buttons() {
		// Falls am Objekt ein StateMachineBehavior haengt wird versucht den momentanen Status aus
		// dem Objekt auszulesen und die entsprechenden Buttons aus dem Behavior hinzuzufuegen.
		if (!$this->smb_buttons_set) {
			if (is_null($smb = $this->get_meta_object()->get_behaviors()->get_by_alias('exface.Core.Behaviors.StateMachineBehavior'))) {
				throw new MetaModelBehaviorException('StateMenuButton: The object '.$this->get_meta_object()->get_alias_with_namespace().' has no StateMachineBehavior attached.');
			}
			
			if (($data_sheet = $this->get_prefill_data()) && ($state_column = $data_sheet->get_column_values($smb->get_state_attribute_alias()))) {
				$current_state = $state_column[0];
			} else {
				$current_state = $smb->get_default_state_id();
			}

            $states = $smb->get_states();

			$button_widget = $this->get_input_widget()->get_button_widget_type();
			foreach ($smb->get_state_buttons($current_state) as $target_state => $smb_button) {
				// Ist show_states leer werden alle Buttons hinzugefuegt (default)
				// sonst wird der Knopf nur hinzugefuegt wenn er in show_states enthalten ist.
				if (empty($this->get_show_states()) || in_array($target_state, $this->get_show_states())) {
					// Die Action des StateMenuButtons wird fuer die einzelnen Buttons uebernommen.
					// Das UxonObject wird weiter im urspruenglichen Zustand benoetigt, daher wird
					// der Action-Alias nur temporaer gesetzt.
					// TODO: Das UxonObject sollte besser vor der Aktion kopiert und mit der Kopie
					// gearbeitet werden. Das Problem ist dass die referenzierten Objekte der
					// Kopie die selben wie beim Orginal sind, daher das Orginal verändert wird
					// wenn man mit der Kopie arbeitet.
					if (!is_null($this->get_action_alias())) {
						$action_alias_temp = $smb_button->action->alias;
						$refresh_widget_link_temp = $smb_button->refresh_widget_link;
						
						$smb_button->action->alias = $this->get_action_alias();
						if (!is_null($this->get_refresh_widget_link())) {
							$smb_button->refresh_widget_link = $this->get_refresh_widget_link()->export_uxon_object();
						}
						
						$button = $this->get_page()->create_widget($button_widget, $this, UxonObject::from_anything($smb_button));
						
						$smb_button->action->alias = $action_alias_temp;
						$smb_button->refresh_widget_link = $refresh_widget_link_temp;
						
						// TODO das wäre schöner, muss aber erst ausprobiert werden!
						/* @var $uxon \exface\Core\CommonLogic\UxonObject */
						// $uxon = $this->get_original_uxon_object()->extend(UxonObject::from_anything($smb_button)->copy());
						// $button = $this->get_page()->create_widget($button_widget, $this, $uxon);
						
					} else {
						$button = $this->get_page()->create_widget($button_widget, $this, UxonObject::from_anything($smb_button));
					}

                    /** @var StateMachineState $stateObject */
                    $stateObject = $states[$target_state];
					$name = $stateObject->getStateName($this->get_meta_object()->get_app()->get_translator());
					if ($name)
						$button->set_caption($name);

					$this->add_button($button);
				}
			}
			
			$this->smb_buttons_set = true;
		}
		
		return parent::get_buttons();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\Button::get_caption()
	 */
	public function get_caption(){
		$caption = parent::get_caption();
		if (!$caption && !$this->get_hide_caption()){
			$caption = $this->get_workbench()->get_core_app()->get_translator()->translate('WIDGET.STATEMENUBUTTON.CAPTION');		
		}
		return $caption;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\MenuButton::get_children()
	 */
	public function get_children() {
		if (!$this->smb_buttons_set) { $this->get_buttons(); }
		return parent::get_children();
	}
	
	/**
	 * Returns the states that are shown.
	 * 
	 * @return integer[]|string[]
	 */
	public function get_show_states() {
		return $this->show_states;
	}
	
	/**
	 * Defines a number of states for which transition buttons are shown.
	 * By default all buttons defined for the current state are shown.
	 * 
	 * @param integer[]|string[] $value
	 * @return \exface\Core\Widgets\StateMenuButton
	 */
	public function set_show_states($value) {
		$this->show_states = $value;
		return $this;
	}
}
?>
