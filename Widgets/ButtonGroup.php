<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iTriggerAction;
class ButtonGroup extends AbstractWidget implements iHaveButtons, iHaveIcon {
	private $buttons =  array();
	private $icon_name = null;
	private $input_widget = null;
	
	private $smb_buttons = array();
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::get_buttons()
	 */
	public function get_buttons() {
		//Falls am Objekt ein StateMachineBehavior haengt wird versucht den momentanen Status aus
		//dem Objekt auszulesen und die entsprechenden Buttons aus dem Behavior hinzuzufuegen
		if (!$this->smb_buttons && ($smb = $this->get_meta_object()->get_behaviors()->get_by_alias('exface.Core.Behaviors.StateMachineBehavior'))) {
			$template = $this->get_ui()->get_template_from_request();
			if ((($data_sheet = $this->get_prefill_data()) || ($data_sheet = $template->get_data_sheet_from_request($template->get_request_object_id())))
					&& ($state_column = $data_sheet->get_column_values($smb->get_state_attribute_alias()))) {
				$current_state = $state_column[0];
			} else {
				$current_state = $smb::DEFAULT_STATE;
			}
			
			if ($smb_buttons = $smb->get_state_buttons($current_state)) {
				foreach ($smb_buttons as $smb_button) {
					$button = $this->get_page()->create_widget('Button', $this, UxonObject::from_anything($smb_button));
					$button->set_parent($this);
					$button->set_input_widget($this->get_input_widget());
					$this->smb_buttons[] = $button;
				}
			}
		}
		
		return array_merge($this->buttons, $this->smb_buttons);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::set_buttons()
	 */
	public function set_buttons(array $buttons_array) {
		if (!is_array($buttons_array)) return false;
		foreach ($buttons_array as $b){
			$button = $this->get_page()->create_widget('Button', $this, UxonObject::from_anything($b));
			$this->add_button($button);
		}
	}
	
	/**
	 * Adds a button to the group
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::add_button()
	 */
	public function add_button(Button $button_widget){
		$button_widget->set_parent($this);
		$button_widget->set_input_widget($this->get_input_widget());
		$this->buttons[] = $button_widget;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveIcon::get_icon_name()
	 */
	public function get_icon_name() {
		return $this->icon_name;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveIcon::set_icon_name()
	 */
	public function set_icon_name($value) {
		$this->icon_name = $value;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Widgets\AbstractWidget::get_children()
	 */
	public function get_children() {
		return $this->get_buttons();
	}
	
	/**
	 * Returns the input widget for buttons in this group. That is the widget, that holds the data,
	 * the button's actions are supposed to be performed upon. Since button groups can be nested, we
	 * need to travel up all the group hierarchy to the first parent, which is not a button group and
	 * thus contains all the buttons (or would contain them if there were no groups).
	 */
	protected function get_input_widget(){
		if (!$this->input_widget){
			$parent = $this;
			while (!$parent instanceof Dialog && !$parent instanceof DataTable && !is_null($parent->get_parent())) {
				$parent = $parent->get_parent();
			}
			$this->input_widget = $parent;
		}
		return $this->input_widget;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::has_buttons()
	 */
	public function has_buttons() {
		if (count($this->buttons)) return true;
		else return false;
	}
}
?>