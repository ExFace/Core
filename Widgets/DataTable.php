<?php namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveTopToolbar;
use exface\Core\Interfaces\Widgets\iHaveBottomToolbar;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;

/**
 * Renders data as a table with filters, columns, and toolbars. Columns of the DataTable can also be made editable.
 * 
 * Example: 
 * 	{
 * 	  "id": "attributes",
 * 	  "widget_type": "DataTable",
 * 	  "object_alias": "exface.Core.ATTRIBUTE",
 * 	  "filters": [
 * 	    {
 * 	      "attribute_alias": "OBJECT"
 * 	    },
 * 	    {
 * 	      "attribute_alias": "OBJECT__DATA_SOURCE"
 * 	    }
 * 	  ],
 * 	  "columns": [
 * 	    {
 * 	      "attribute_alias": "OBJECT__LABEL"
 * 	    },
 * 	    {
 * 	      "attribute_alias": "LABEL"
 * 	    },
 * 	    {
 * 	      "attribute_alias": "ALIAS"
 * 	    },
 * 	    {
 * 	      "attribute_alias": "RELATED_OBJ__LABEL",
 * 	      "caption": "Relation to"
 * 	    }
 * 	  ],
 * 	  "buttons": [
 * 	    {
 * 	      "action_alias": "exface.Core.UpdateData"
 * 	    },
 * 	    {
 * 	      "action_alias": "exface.Core.CreateObjectDialog",
 * 	      "caption": "Neu"
 * 	    },
 * 	    {
 * 	      "action_alias": "exface.Core.EditObjectDialog",
 * 	      "bind_to_double_click": true
 * 	    },
 * 	    {
 * 	      "action_alias": "exface.Core.DeleteObject"
 * 	    }
 * 	  ]
 * 	}
 * 
 * @author Andrej Kabachnik
 *
 */
class DataTable extends Data implements iHaveTopToolbar, iHaveBottomToolbar, iFillEntireContainer, iSupportMultiSelect {
	
	private $show_filter_row = false;
	private $show_row_numbers = false;
	private $multi_select = false;
	private $striped = true;
	private $nowrap = true;
	private $auto_row_height = true;
	
	private $hide_toolbar_top = false;
	private $hide_toolbar_bottom = false;
	
	private $row_details_container = null;
	private $row_details_action = 'exface.Core.ShowWidget';
	
	private $row_groups_by_column_id = null;
	private $row_groups_expand = 'all';
	private $row_groups_show_count = true;
	
	private $context_menu_enabled = true;
	private $header_sort_multiple = false;
	
	function has_row_details(){
		if (!$this->row_details_container) return false;
		else return true;
	}
	
	function has_row_groups(){
		if ($this->get_row_groups_by_column_id()) return true;
		else return false;
	}
	
	/**
	 * Makes each row have a collapsible detail container with arbitrary widgets. 
	 * 
	 * Most templates will render an expand-button in each row, allowing to expand/collapse the detail widget.
	 * This only works with interactiv templates (e.g. HTML-templates)
	 * 
	 * The widget type of the details-widget can be omitted. It defaults to Container in this case.
	 * 
	 * Example:
	 * 	{
	 * 		height: nnn
	 * 		widgets: [ ... ]
	 * 	}
	 * 
	 * @uxon-property row_details
	 * @uxon-type \exface\Core\Widgets\Container
	 * 
	 * @param  $detail_widget
	 * @return boolean
	 */
	function set_row_details(\stdClass $detail_widget){
		$page = $this->get_page();
		if (!$detail_widget->widget_type){
			$detail_widget->widget_type = 'Container';
		}
		$widget = WidgetFactory::create_from_uxon($page, $detail_widget, $this);
		if ($widget instanceof Container){
			$container = $widget;
		} else {
			$container = $this->get_page()->create_widget('Container', $this);
			$container->add_widget($widget);
		}
		$this->set_row_details_container($container);
	}
	
	public function get_children(){
		$children = parent::get_children();
		if ($this->has_row_details()){
			$children[] = $this->get_row_details_container();
		}
		return $children;
	}
	
	public function get_row_details_container() {
		return $this->row_details_container;
	} 
	
	/**
	 * 
	 * @param Container $widget
	 */
	public function set_row_details_container(Container $widget){
		$this->row_details_container = $widget;
	}
	
	/**
	 * Makes the table group rows by values of a column. Each group will have a header and will be collapsible.
	 * 
	 * It is a good idea to give the column, that will be used for grouping an explicit id. This id is then
	 * what you need to specify in group_by_column_id. In most cases, the column used for grouping will be
	 * hidden, because it does not make much sens to show it's values within every group as they are the same
	 * in the group and are also visible in the group's title.
	 * 
	 * Set "expand" to FALSE to collapse all groups initially. Set "show_count" to TRUE to include the number
	 * of rows within the group in it's header. 
	 * 
	 * Example:
	 * 	"group_rows": {
	 * 		"group_by_column_id": "my_column_id",
	 * 		"expand": true,
	 * 		"show_count": true
	 * 		"action_alias": "exface.Core.ShowWidget"
	 * 	}
	 * 
	 * @uxon-property group_rows
	 * @uxon-type Object
	 * 
	 * @param \stdClass $uxon_description_object
	 * @return DataTable
	 */
	public function set_group_rows(\stdClass $uxon_description_object){
		if (isset($uxon_description_object->group_by_column_id)) $this->set_row_groups_by_column_id($uxon_description_object->group_by_column_id);
		if (isset($uxon_description_object->expand)) $this->set_row_groups_expand($uxon_description_object->expand);
		if (isset($uxon_description_object->show_count)) $this->set_row_groups_show_count($uxon_description_object->show_count);
		if (isset($uxon_description_object->action_alias)) $this->set_row_details_action($uxon_description_object->action_alias);
		return $this;
	}
	
	public function get_row_groups_by_column_id() {
		return $this->row_groups_by_column_id;
	}
	
	public function set_row_groups_by_column_id($value) {
		$this->row_groups_by_column_id = $value;
	}
	
	public function get_row_groups_expand() {
		return $this->row_groups_expand;
	}
	
	public function set_row_groups_expand($value) {
		$this->row_groups_expand = $value;
	}     
	
	public function get_row_groups_show_count() {
		return $this->row_groups_show_count;
	}
	
	public function set_row_groups_show_count($value) {
		$this->row_groups_show_count = $value;
	}  
	
	public function get_context_menu_enabled() {
		return $this->context_menu_enabled;
	}
	
	/**
	 * Set to FALSE to disable the context (right-click) menu for rows.
	 * 
	 * @uxon-property context_menu_enabled
	 * @uxon-type boolean
	 * 
	 * @param boolean $value
	 * @return DataTable
	 */
	public function set_context_menu_enabled($value) {
		$this->context_menu_enabled = $value ? true : false;
		return $this;
	}

	public function get_show_filter_row() {
		return $this->show_filter_row;
	}
	
	/**
	 * Set to TRUE to show a special row with filters for each column (if supported by the template).
	 * 
	 * This is a handy alternative to defining filter individually.
	 * 
	 * @uxon-property show_filter_row
	 * @uxon-type boolean
	 * 
	 * @param boolean $value
	 * @return DataTable
	 */
	public function set_show_filter_row($value) {
		$this->show_filter_row = $value ? true : false;
		return $this;
	} 

	public function get_header_sort_multiple() {
		return $this->header_sort_multiple;
	}
	
	/**
	 * Set to TRUE to enable click-sorting via column headers for multiple columns simultanuosly (if supported by template)
	 * 
	 * @uxon-property header_sort_multiple
	 * @uxon-type boolean
	 * 
	 * @param boolean $value
	 * @return DataTable
	 */
	public function set_header_sort_multiple($value) {
		$this->header_sort_multiple = $value ? true : false;
		return $this;
	}  
	
	public function get_width(){
		if (parent::get_width()->is_undefined()){
			$this->set_width('max');
		}
		return parent::get_width();
	}
	
	public function get_row_details_action() {
		return $this->row_details_action;
	}
	
	public function set_row_details_action($value) {
		$this->row_details_action = $value;
		return $this;
	}  
	
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
		$this->hide_toolbar_top = $value ? true : false;
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
		$this->hide_toolbar_bottom = $value ? true : false;
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
	
	public function get_show_row_numbers() {
		return $this->show_row_numbers;
	}
	
	/**
	 * Set to TRUE to show the row number for each row.
	 * 
	 * @uxon-property show_row_numbers
	 * @uxon-type boolean
	 * 
	 * @param boolean $value
	 * @return DataTable
	 */
	public function set_show_row_numbers($value) {
		$this->show_row_numbers = $value;
		return $this;
	}
	
	public function get_nowrap() {
		return $this->nowrap;
	}
	
	/**
	 * Set to TRUE to disable text wrapping in all columns. Each column will have only one line then.
	 * 
	 * @uxon-property nowrap
	 * @uxon-type boolean
	 * 
	 * @param boolean $value
	 * @return \exface\Core\Widgets\DataTable
	 */
	public function set_nowrap($value) {
		$this->nowrap = $value ? true : false;
		return $this;
	}
	
	public function get_striped() {
		return $this->striped;
	}
	
	/**
	 * Set to TRUE to make the rows background color alternate.
	 * 
	 * @uxon-property striped
	 * @uxon-type boolean
	 * 
	 * @param boolean $value
	 * @return \exface\Core\Widgets\DataTable
	 */
	public function set_striped($value) {
		$this->striped = $value ? true : false;
		return $this;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function get_auto_row_height() {
		return $this->auto_row_height;
	}
	
	/**
	 * Set to FALSE to prevent automatic hight adjustment for rows. Each row will have the height of one line.
	 * 
	 * @uxon-property auto_row_height
	 * @uxon-type boolean
	 * 
	 * @param boolean $value
	 * @return \exface\Core\Widgets\DataTable
	 */
	public function set_auto_row_height($value) {
		$this->auto_row_height = $value ? true : false;
		return $this;
	}
	
	public function get_multi_select() {
		return $this->multi_select;
	}
	
	/**
	 * Set to TRUE to allow selecting multiple rows at a time and FALSE to force selection of exactly one row.
	 *
	 * @uxon-property multi_select
	 * @uxon-type boolean
	 *
	 * @see \exface\Core\Interfaces\Widgets\iSupportMultiSelect::set_multi_select()
	 */
	public function set_multi_select($value) {
		$this->multi_select = $value;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::get_alternative_container_for_orphaned_siblings()
	 */
	public function get_alternative_container_for_orphaned_siblings(){
		return null;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveValues::get_values()
	 */
	public function get_values(){
		// TODO set selected table rows programmatically
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
		// TODO set selected table rows programmatically
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