<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iHaveTopToolbar;
use exface\Core\Interfaces\Widgets\iHaveBottomToolbar;
use exface\Core\Model\RelationPath;
use exface\Core\Factories\WidgetFactory;

class DataTable extends Data implements iHaveTopToolbar, iHaveBottomToolbar {
	/**
	 * Container-Widget, that holds everything to display in the optional row details (e.g. expandable rows)
	 * @var Container
	 */
	protected $show_filter_row = false;
	
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
	 * Parses a UXON widget object, which contains widgets to show in the row details
	 * Structure:
	 * {
	 * 	height: nnn
	 * 	widgets: [ ]
	 * }
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
	
	public function set_row_details_container(Container $widget){
		$this->row_details_container = $widget;
	}
	
	public function set_group_rows(\stdClass $uxon_description_object){
		if (isset($uxon_description_object->group_by_column_id)) $this->set_row_groups_by_column_id($uxon_description_object->group_by_column_id);
		if (isset($uxon_description_object->expand)) $this->set_row_groups_expand($uxon_description_object->expand);
		if (isset($uxon_description_object->show_count)) $this->set_row_groups_show_count($uxon_description_object->show_count);
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
	
	public function set_context_menu_enabled($value) {
		$this->context_menu_enabled = $value;
	}

	public function get_show_filter_row() {
		return $this->show_filter_row;
	}
	
	public function set_show_filter_row($value) {
		$this->show_filter_row = $value;
	} 

	public function get_header_sort_multiple() {
		return $this->header_sort_multiple;
	}
	
	public function set_header_sort_multiple($value) {
		$this->header_sort_multiple = $value;
	}  
	
	public function get_width(){
		if (!$this->width){
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
	
	public function set_hide_toolbar_top($value) {
		$this->hide_toolbar_top = $value;
		return $this;
	}
	
	public function get_hide_toolbar_bottom() {
		return $this->hide_toolbar_bottom;
	}
	
	public function set_hide_toolbar_bottom($value) {
		$this->hide_toolbar_bottom = $value;
		return $this;
	}
	
	public function get_hide_toolbars() {
		return ($this->get_hide_toolbar_top() && $this->get_hide_toolbar_bottom());
	}
	
	public function set_hide_toolbars($value) {
		$this->set_hide_toolbar_top($value);
		$this->set_hide_toolbar_bottom($value);
		return $this;
	}	    
}
?>