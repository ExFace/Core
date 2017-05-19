<?php namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iAmClosable;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Interfaces\Widgets\iHaveContextualHelp;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;

class Dialog extends Form implements iAmClosable, iHaveContextualHelp {
	private $hide_close_button = false;
	private $close_button = null;
	private $maximizable = true;
	private $maximized = false;
	
	private $help_button = null;
	private $hide_help_button = false;
	
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
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\Container::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = parent::export_uxon_object();
		// TODO add properties specific to this widget here
		return $uxon;
	}
	
	public function get_help_button(){
		if (is_null($this->help_button)){
			$this->help_button = WidgetFactory::create($this->get_page(), $this->get_button_widget_type(), $this);
			$this->help_button->set_action_alias('exface.Core.ShowHelpDialog');
			$this->help_button->set_close_dialog_after_action_succeeds(false);
		}
		return $this->help_button;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::get_help_widget()
	 */
	public function get_help_widget(iContainOtherWidgets $help_container){
		/**
		 * @var DataTable $table
		 */
		$table = WidgetFactory::create($help_container->get_page(), 'DataTable', $help_container);
		$object = $this->get_workbench()->model()->get_object('exface.Core.USER_HELP_ELEMENT');
		$table->set_meta_object($object);
		$table->set_caption($this->get_widget_type(). ($this->get_caption() ? '"' . $this->get_caption() . '"' : ''));
		$table->add_column($table->create_column_from_attribute($object->get_attribute('TITLE')));
		$table->add_column($table->create_column_from_attribute($object->get_attribute('DESCRIPTION')));
		$table->set_lazy_loading(false);
		$table->set_paginate(false);
		$table->set_nowrap(false);
		// $table->set_group_rows(UxonObject::from_array(array('group_by_column_id' => 'GROUP')));
		
		// IMPORTANT: make sure the help table does not have a help button itself, because that would result in having
		// infinite children!
		$table->set_hide_help_button(true);
		
		$data_sheet = DataSheetFactory::create_from_object($object);
		
		foreach ($this->get_input_widgets() as $widget){
			if ($widget->is_hidden()) continue;
			$row = array('TITLE' => $widget->get_caption());
			if ($widget instanceof iShowSingleAttribute && $attr = $widget->get_attribute()){
				$row = array_merge($row, $this->get_help_row_from_attribute($attr));
			}
			$data_sheet->add_row($row);
		}
		
		$table->prefill($data_sheet);
		
		$help_container->add_widget($table);
		return $help_container;
	}
	
	/**
	 * Returns a row (assotiative array) for a data sheet with exface.Core.USER_HELP_ELEMENT filled with information about
	 * the given attribute. The inforation is derived from the attributes meta model.
	 *
	 * @param Attribute $attr
	 * @return string[]
	 */
	protected function get_help_row_from_attribute(Attribute $attr){
		$row = array();
		$row['DESCRIPTION'] = $attr->get_short_description() ? rtrim(trim($attr->get_short_description()), ".") . '.' : '';
		
		if (!$attr->get_relation_path()->is_empty()){
			$row['DESCRIPTION'] .=  $attr->get_object()->get_short_description() ? ' ' . rtrim($attr->get_object()->get_short_description(), ".") . '.' : '';
		}
		return $row;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::get_hide_help_button()
	 */
	public function get_hide_help_button() {
		if (!$this->hide_help_button && count($this->get_input_widgets()) == 0){
			$this->hide_help_button = true;
		}
		return $this->hide_help_button;
	}
	
	/**
	 * Set to TRUE to remove the contextual help button. Default: FALSE.
	 *
	 * @uxon-property hide_help_button
	 * @uxon-type boolean
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::set_hide_help_button()
	 */
	public function set_hide_help_button($value) {
		$this->hide_help_button = BooleanDataType::parse($value);
		return $this;
	}
	
	public function get_children(){
		$children = parent::get_children();
		
		// Add the help button, so pages will be able to find it when dealing with the ShowHelpDialog action.
		// IMPORTANT: Add the help button to the children only if it is not hidden. This is needed to hide the button in
		// help widgets themselves, because otherwise they would produce their own help widgets, with - in turn - even
		// more help widgets, resulting in an infinite loop.
		if (!$this->get_hide_help_button()){
			$children[] = $this->get_help_button();
		}
		return $children;
	}
}
?>