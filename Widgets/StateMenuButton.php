<?php
namespace exface\Core\Widgets;
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
			if ($smb = $this->get_meta_object()->get_behaviors()->get_by_alias('exface.Core.Behaviors.StateMachineBehavior')) {
				$template = $this->get_ui()->get_template_from_request();
				if (($data_sheet = $this->get_prefill_data()) && ($state_column = $data_sheet->get_column_values($smb->get_state_attribute_alias()))) {
					$current_state = $state_column[0];
				} else {
					$current_state = $smb->get_default_state_id();
				}
				
				$button_widget = $this->get_input_widget()->get_button_widget_type();
				$action_alias = $this->get_action_alias();
				foreach ($smb->get_state_buttons($current_state) as $target_state => $smb_button) {
					// Ist eine Action fuer den StateMenuButton definiert, so wird sie fuer die einzelnen Knoepfe
					// uebernommen.
					if (!is_null($action_alias)) { $smb_button->action->alias = $action_alias; }
					// Nur diejenigen Buttons hinzufuegen, welche in show_states definiert sind.
					if (empty($this->get_show_states()) || in_array($target_state, $this->get_show_states())) {
						$button = $this->get_page()->create_widget($button_widget, $this, UxonObject::from_anything($smb_button));
						$this->add_button($button);
					}
				}
				
				$this->smb_buttons_set = true;
			} else {
				throw new MetaModelBehaviorException('StateMenuButton: The object '.$this->get_meta_object()->get_alias_with_namespace().' has no StateMachineBehavior attached.');
			}
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
	 *
	 * @return integer[]|string[]
	 */
	public function get_show_states() {
		return $this->show_states;
	}
	
	/**
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
