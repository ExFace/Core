<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iHaveTopToolbar;
use exface\Core\Interfaces\Widgets\iHaveBottomToolbar;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;

/**
 * Similar to a DataTable, but displaying each element as a tile or card instead of a table row.
 * 
 * The contents is still defined via columns, filters, buttons, etc. It's just the visual appearance, that
 * is different.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataList extends Data implements iHaveTopToolbar, iHaveBottomToolbar, iFillEntireContainer, iSupportMultiSelect {
	private $hide_toolbar_top = false;
	private $hide_toolbar_bottom = false;
	private $multi_select = false;
	
	public function get_hide_toolbar_top() {
		return $this->hide_toolbar_top;
	}
	
	/**
	 * Set to TRUE to hide the top toolbar or FALSE to show it.
	 * 
	 * @uxon-property hide_toolbar_top
	 * @uxon-type boolean
	 * 
	 * @see \exface\Core\Interfaces\Widgets\iHaveTopToolbar::set_hide_toolbar_top()
	 */
	public function set_hide_toolbar_top($value) {
		$this->hide_toolbar_top = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}
	
	public function get_hide_toolbar_bottom() {
		return $this->hide_toolbar_bottom;
	}
	
	/**
	 * Set to TRUE to hide the bottom toolbar or FALSE to show it.
	 *
	 * @uxon-property hide_toolbar_bottom
	 * @uxon-type boolean
	 *
	 * @see \exface\Core\Interfaces\Widgets\iHaveTopToolbar::set_hide_toolbar_top()
	 */
	public function set_hide_toolbar_bottom($value) {
		$this->hide_toolbar_bottom = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}
	
	public function get_hide_toolbars() {
		return ($this->get_hide_toolbar_top() && $this->get_hide_toolbar_bottom());
	}
	
	/**
	 * Set to TRUE to hide the all toolbars. Use hide_toolbar_top and hide_toolbar_bottom to control toolbar individually.
	 *
	 * @uxon-property hide_toolbars
	 * @uxon-type boolean
	 *
	 * @see \exface\Core\Interfaces\Widgets\iHaveTopToolbar::set_hide_toolbar_top()
	 */
	public function set_hide_toolbars($value) {
		$this->set_hide_toolbar_top($value);
		$this->set_hide_toolbar_bottom($value);
		return $this;
	}
	
	public function get_width(){
		if (parent::get_width()->is_undefined()){
			$this->set_width('max');
		}
		return parent::get_width();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::get_alternative_container_for_orphaned_siblings()
	 */
	public function get_alternative_container_for_orphaned_siblings(){
		return null;
	}
	
	public function get_multi_select() {
		return $this->multi_select;
	}
	
	/**
	 * Set to TRUE to allow selecting multiple elements at a time and FALSE to force selection of exactly one element.
	 * 
	 * @uxon-property multi_select
	 * @uxon-type boolean
	 * 
	 * @see \exface\Core\Interfaces\Widgets\iSupportMultiSelect::set_multi_select()
	 */
	public function set_multi_select($value) {
		$this->multi_select = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}	  
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveValues::get_values()
	 */
	public function get_values(){
		// TODO set selected list items programmatically
		/*
		if ($this->get_value()){
		return explode(EXF_LIST_SEPARATOR, $this->get_value());
		} */
		return array();
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveValues::set_values()
	 */
	public function set_values($expression_or_delimited_list){
		// TODO set selected list items programmatically
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveValues::set_values_from_array()
	 */
	public function set_values_from_array(array $values){
		$this->set_value(implode(EXF_LIST_SEPARATOR, $values));
		return $this;
	}
}
?>