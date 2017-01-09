<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iHaveMenu;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * A button with a menu, containing other buttons.
 * 
 * If the MenuButton itself does not have an action, pressing it will merely open the menu. With an action defined,
 * the MenuButton will have actually two functions: performing the action and opening the menu with other buttons.
 * In the latter case, most templates will render a split button with a larger area for the action and a smaller
 * area for the menu - e.g. multitool buttons with little triangles on the right in MS Word, Photoshop, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
class MenuButton extends Button	implements iHaveMenu, iHaveButtons {
	
	/** @var Menu $menu */
	private $menu = null;
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveMenu::get_menu()
	 */
	public function get_menu() {
		if (is_null($this->menu)){
			$page = $this->get_page();
			$this->set_menu(WidgetFactory::create($page, 'Menu', $this));
		}
		return $this->menu;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveMenu::set_menu()
	 */
	public function set_menu($menu_widget_or_uxon_or_array) {
		if ($menu_widget_or_uxon_or_array instanceof Menu){
			$this->menu = $menu_widget_or_uxon_or_array;
			$this->menu->set_parent($this);
			$this->menu->set_input_widget($this->get_input_widget());
		} elseif (is_array($menu_widget_or_uxon_or_array)){
			$this->get_menu()->set_buttons($menu_widget_or_uxon_or_array);
		} elseif ($menu_widget_or_uxon_or_array instanceof \stdClass){
			$this->get_menu()->import_uxon_object($menu_widget_or_uxon_or_array);
		} else {
			throw new WidgetPropertyInvalidValueError($this, 'Invalid menu configuration for MenuButton "' . $this->get_id() . '"!');
		}
		return $this;
	}	  
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::get_buttons()
	 */
	public function get_buttons() {
		return $this->get_menu()->get_buttons();
	}
	
	/**
	 * Defines the buttons in the menu via array of button definitions.
	 * 
	 * @uxon-property buttons
	 * @uxon-type Button[]
	 * 
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::set_buttons()
	 */
	public function set_buttons(array $buttons_array) {
		return $this->get_menu()->set_buttons($buttons_array);
	}
	
	/**
	 * Adds a button to the group
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::add_button()
	 */
	public function add_button(Button $button_widget){
		$this->get_menu()->add_button($button_widget);
		return $this;
	}
	
	/**
	 * Removes a button from the group
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::remove_button()
	 */
	public function remove_button(Button $button_widget){
		$this->get_menu()->remove_button($button_widget);
		return $this;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Widgets\AbstractWidget::get_children()
	 */
	public function get_children() {
		return array_merge(parent::get_children(), array($this->get_menu()));
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::has_buttons()
	 */
	public function has_buttons() {
		if ($this->get_menu()->has_buttons()) {
			return true;
		}
		return false;
	}
}
?>