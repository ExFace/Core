<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\CommonLogic\UxonObject;

/**
 * A group of button widgets with a mutual input widget. 
 * 
 * Depending on the template, a ButtonGroup can be displayed as a list of buttons or even transformed to a menu.
 * 
 * @author Andrej Kabachnik
 *
 */
class ButtonGroup extends Button implements iHaveButtons {
	private $buttons =  array();
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::get_buttons()
	 */
	public function get_buttons() {
		return $this->buttons;
	}
	
	/**
	 * Defines the contained buttons via array of button definitions.
	 * 
	 * @uxon-property buttons
	 * @uxon-type Button[]
	 * 
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
		return $this;
	}
	
	/**
	 * Removes a button from the group
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::remove_button()
	 */
	public function remove_button(Button $button_widget){
		if(($key = array_search($button_widget, $this->buttons)) !== false) {
			unset($this->buttons[$key]);
		}
		return $this;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Widgets\AbstractWidget::get_children()
	 */
	public function get_children() {
		return array_merge(parent::get_children(), $this->get_buttons());
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