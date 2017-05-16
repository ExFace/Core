<?php namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iLayoutWidgets;
use exface\Core\Interfaces\Widgets\iAmCollapsible;

/**
 * A panel is a visible container with a configurable layout, support for lazy-loading of content.
 * 
 * The panel is the base widget for many containers, that show multiple smaller widgets in a column-based 
 * (newspaper-like) layout.
 * 
 * @see Form - Panel with buttons
 * @see InputGroup - Small panel to easily group input widgets
 * @see SplitPanel - Special resizable panel to be used in SplitVertical and SplitHorizontal widgets
 * @see Tab - Special panel to be used in the Tabs widget
 * 
 * @author Andrej Kabachnik
 *
 */
class Panel extends Container implements iLayoutWidgets, iSupportLazyLoading, iHaveIcon, iAmCollapsible, iFillEntireContainer {
	
	private $lazy_loading = false; // A panel will not be loaded via AJAX by default
	private $lazy_loading_action = 'exface.Core.ShowWidget';
	private $collapsible = false;
	private $icon_name = null;
	private $column_number = null;
	private $column_stack_on_smartphones = null;
	private $column_stack_on_tablets = null;
	private $lazy_loading_group_id = null;
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::get_collapsible()
	 */
	public function get_collapsible() {
		return $this->collapsible;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::set_collapsible()
	 */
	public function set_collapsible($value) {
		$this->collapsible = $value;
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
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::get_lazy_loading()
	 */
	public function get_lazy_loading() {
		return $this->lazy_loading;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::set_lazy_loading()
	 */
	public function set_lazy_loading($value) {
		$this->lazy_loading = $value;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::get_lazy_loading_action()
	 */
	public function get_lazy_loading_action() {
		return $this->lazy_loading_action;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::set_lazy_loading_action()
	 */
	public function set_lazy_loading_action($value) {
		$this->lazy_loading_action = $value;
		return $this;
	} 
	
	public function get_column_number() {
		return $this->column_number;
	}
	
	public function set_column_number($value) {
		$this->column_number = $value;
		return $this;
	} 
	
	/**
	 * Returns TRUE if the columns should be stacked on small screens and FALSE otherwise. Returns NULL if the creator of the widget
	 * had made no preference and thus the stacking is completely upto the template.
	 * @return boolean
	 */
	public function get_column_stack_on_smartphones() {
		return $this->column_stack_on_smartphones;
	}
	
	/**
	 * Determines wether columns should be stacked on smaller screens (TRUE) or left side-by-side (FALSE). Setting this to NULL will
	 * leave it upto the template to decide.
	 * @param boolean $value
	 */
	public function set_column_stack_on_smartphones($value) {
		$this->column_stack_on_smartphones = $value;
		return $this;
	}  
	
	/**
	 * Returns TRUE if the columns should be stacked on midsize screens and FALSE otherwise. Returns NULL if the creator of the widget
	 * had made no preference and thus the stacking is completely upto the template.
	 * @return boolean
	 */
	public function get_column_stack_on_tablets() {
		return $this->column_stack_on_tablets;
	}
	
	/**
	 * Determines wether columns should be stacked on midsize screens (TRUE) or left side-by-side (FALSE). Setting this to NULL will
	 * leave it upto the template to decide.
	 * @param boolean $value
	 */
	public function set_column_stack_on_tablets($value) {
		$this->column_stack_on_tablets = $value;
		return $this;
	} 
	
	/**
	 * {@inheritDoc}
	 * 
	 * If the parent widget of a panel has other children (siblings of the panel), they should be moved to the panel itself, once it is
	 * added to it's paren.
	 * 
	 * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::get_alternative_container_for_orphaned_siblings()
	 * @return Panel
	 */
	public function get_alternative_container_for_orphaned_siblings(){
		return $this;
	}
	
	public function get_lazy_loading_group_id(){
		return $this->lazy_loading_group_id;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::set_lazy_loading_group_id()
	 */
	public function set_lazy_loading_group_id($value){
		$this->lazy_loading_group_id = $value;
		return $this;
	}
}
?>