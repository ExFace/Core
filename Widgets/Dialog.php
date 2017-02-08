<?php namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iAmClosable;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\CommonLogic\Model\Attribute;

class Dialog extends Form implements iAmClosable {
	private $hide_close_button = false;
	private $close_button = null;
	private $maximizable = true;
	private $maximized = false;
	
	protected function init(){
		parent::init();
		$this->set_lazy_loading(true);
		$this->set_button_widget_type('DialogButton');
	}
	
	/**
	 * Adds a widget to the dialog. Widgets, that fill a container completely, will be added as the only child of the
	 * dialog, while any already present children will be moved to the filling widget automatically. Thus, if a panel
	 * or the tabs widget are added, they will be the only child of the dialog and will wrap all other widgets within
	 * it - even if those other children were added earlier!
	 * 
	 * @see Panel::add_widget()
	 */
	public function add_widget(AbstractWidget $widget, $position = NULL){
		if ($widget instanceof iFillEntireContainer){
			if ($container = $widget->get_alternative_container_for_orphaned_siblings()){
				foreach ($this->get_widgets() as $w){
					$container->add_widget($w);
				}
				parent::remove_widgets();
			}
		} 
		return parent::add_widget($widget, $position);
	}
	
	/**
	 * If TRUE, the automatically generated close button for the dialog is not shown
	 * @return boolean
	 */
	public function get_hide_close_button() {
		return $this->hide_close_button;
	}
	
	/**
	 * If set to TRUE, the automatically generated close button will not be shown in this dialog
	 * @param boolean $value
	 */
	public function set_hide_close_button($value) {
		$this->hide_close_button = $value;
	}  
	
	/**
	 * Returns a special dialog button, that just closes the dialog without doing any other action
	 * @return \exface\Core\Widgets\DialogButton
	 */
	public function get_close_button(){
		if (!($this->close_button instanceof DialogButton)) {
			/* @var $btn DialogButton */
			$btn = $this->get_page()->create_widget('DialogButton', $this);
			$btn->set_close_dialog_after_action_succeeds(true);
			$btn->set_refresh_input(false);
			$btn->set_icon_name('cancel');
			$btn->set_caption($this->translate('WIDGET.DIALOG.CLOSE_BUTTON_CAPTION'));
			if ($this->get_hide_close_button()) $btn->set_hidden(true);
			$this->close_button = $btn;
		}
		return $this->close_button;
	}
	
	/**
	 * Returns an array of dialog buttons. The close button is always added to the end of the button list.
	 * This ensures, that the other buttons can be rearranged without an impact on the close buttons last
	 * position.
	 * @see \exface\Core\Widgets\Panel::get_buttons()
	 */
	public function get_buttons(){
		$btns = parent::get_buttons();
		$btns[] = $this->get_close_button();
		return $btns;
	}
	
	/**
	 * Returns a Container widget with all widgets the dialog contains. This is usefull for lazy loading dialog contents,
	 * where only the widgets need to be rendered, not the dialog itself. The container is not a regular part of the dialog.
	 * It only gets created if this method is called. It is not added to the dialog, so it will not get listed by get_children(),
	 * etc.
	 * 
	 * When lazy loading the contents of the dialog, it is important to let the template draw() all contained widgets
	 * at once (i.e. draw this container). If we draw each widget individually, the respective template elements will get 
	 * instantiated one after another, so those instatiated first, can't access the ones instantiated later on. Putting 
	 * everything in a container makes the template instatiate all elements before actually drawing them!
	 * 
	 * @return Container
	 */
	public function get_contents_container(){
		$container = $this->get_page()->create_widget('Container', $this);
		foreach ($this->get_widgets() as $w){
			$container->add_widget($w);
		}
		return $container;
	}
	
	public function get_maximizable(){
		return $this->maximizable;
	}
	
	public function set_maximizable($value) {
		$this->maximizable = $value;
		return $this;
	}
	
	public function get_maximized() {
		return $this->maximized;
	}
	
	public function set_maximized($value) {
		$this->maximized = $value;
		return $this;
	}  
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\Container::find_children_by_attribute()
	 */
	public function find_children_by_attribute(Attribute $attribute){
		// If the container has a single filling child, which is a container itself, search that child
		if ($this->count_widgets() == 1){
			$widgets = $this->get_widgets();
			$first_widget = reset($widgets);
			if  ($first_widget instanceof iFillEntireContainer && $first_widget instanceof iContainOtherWidgets){
				return $first_widget->find_children_by_attribute($attribute);
			}
		}
		return parent::find_children_by_attribute($attribute);
	}
}
?>