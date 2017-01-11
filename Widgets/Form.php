<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\CommonLogic\UxonObject;

/**
 * A Form is a Panel with buttons. Forms and their derivatives provide input data for actions.
 * 
 * While having similar purpose as HTML forms, ExFace forms are not the same! They can be nested, they may include tabs, 
 * optional panels with lazy loading, etc. Thus, in most HTML-templates the form widget will not be mapped to an HTML
 * form, but rather to some container element (e.g. <div>), while fetching data from the form will need to be custom
 * implemented (i.e. with JavaScript).
 * 
 * @author Andrej Kabachnik
 *
 */
class Form extends Panel implements iHaveButtons {
	
	private $buttons =  array();
	private $button_widget_type = 'Button'; // Which type of Buttons should be used. Can be overridden by inheriting widgets
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::get_buttons()
	 * @return Button[]
	 */
	public function get_buttons() {
		return $this->buttons;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::set_buttons()
	 */
	public function set_buttons(array $buttons_array) {
		if (!is_array($buttons_array)) return false;
		foreach ($buttons_array as $b){
			// If the widget type of the Button is explicitly defined, use it, otherwise fall back to the button widget type of 
			// this widget: i.e. Button for simple Forms, DialogButton for Dialogs, etc.
			$button_widget_type = property_exists($b, 'widget_type') ? $b->widget_type : $this->get_button_widget_type();
			$button = $this->get_page()->create_widget($button_widget_type, $this, UxonObject::from_anything($b));
			// Add the button to the form
			$this->add_button($button);
		}
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::add_button()
	 */
	public function add_button(Button $button_widget){
		$button_widget->set_parent($this);
		$button_widget->set_meta_object_id($this->get_meta_object()->get_id());
	
		// If the button has an action, that is supposed to modify data, we need to make sure, that the panel
		// contains alls system attributes of the base object, because they may be needed by the business logic
		if ($button_widget->get_action() && $button_widget->get_action()->get_meta_object()->is($this->get_meta_object()) && $button_widget->get_action()->implements_interface('iModifyData')){
			/* @var $attr \exface\Core\CommonLogic\Model\Attribute */
			foreach ($this->get_meta_object()->get_attributes()->get_system() as $attr){
				if (count($this->find_children_by_attribute($attr)) <= 0){
					$widget = $this->get_page()->create_widget('InputHidden', $this);
					$widget->set_attribute_alias($attr->get_alias());
					if ($attr->is_uid_for_object()){
						$widget->set_aggregate_function(EXF_AGGREGATOR_LIST);
					} else {
						$widget->set_aggregate_function($attr->get_default_aggregate_function());
					}
					$this->add_widget($widget);
				}
			}
		}
	
		$this->buttons[] = $button_widget;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::remove_button()
	 */
	public function remove_button(Button $button_widget){
		if(($key = array_search($button_widget, $this->buttons)) !== false) {
			unset($this->buttons[$key]);
		}
		return $this;
	}
	
	/**
	 * Returns the class of the used buttons. Regular panels and forms use ordinarz buttons, but
	 * Dialogs use special DialogButtons capable of closing the Dialog, etc. This special getter
	 * function allows all the logic to be inherited from the panel while just replacing the
	 * button class.
	 * 
	 * @return string
	 */
	public function get_button_widget_type(){
		return $this->button_widget_type;
	}
	
	/**
	 *
	 * @param string $string
	 * @return \exface\Core\Widgets\Panel
	 */
	public function set_button_widget_type($string){
		$this->button_widget_type = $string;
		return $this;
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
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\Container::get_children()
	 */
	public function get_children(){
		return array_merge(parent::get_children(), $this->get_buttons());
	}
}
?>