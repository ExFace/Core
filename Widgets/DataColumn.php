<?php namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Model\DataTypes\AbstractDataType;
use exface\Core\Interfaces\Widgets\iShowText;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Model\Expression;
use exface\Core\Factories\WidgetFactory;
use exface\Core\UxonObject;
use exface\Core\Interfaces\Widgets\iShowDataColumn;

class DataColumn extends AbstractWidget implements iShowDataColumn, iShowSingleAttribute, iShowText {
	private $attribute_alias = null;
	private $sortable = true;
	private $footer = false;
	private $fixed_width = false;
	private $editor = null;
	private $editable = false;
	private $align = null;
	private $aggregate_function = null;
	private $data_type = null;
	private $include_in_quick_search = false;
	private $cell_styler_script = null;
	private $size = null;
	private $style = null;
	private $data_column_name = null;
	
	function has_footer(){
		if (!empty($this->footer)) return true;
		else return false;
	}
	
	public function get_attribute_alias(){
	  return $this->attribute_alias;
	}
	
	public function set_attribute_alias($value) {
	  $this->attribute_alias = $value;
	}
	
	public function get_sortable() {
	  return $this->sortable;
	}
	
	public function set_sortable($value) {
	  $this->sortable = $value;
	}
		
	public function get_footer() {
	  return $this->footer;
	}
	
	public function set_footer($value) {
	  $this->footer = $value;
	} 

	public function get_fixed_width() {
		return $this->fixed_width;
	}
	
	public function set_fixed_width($value) {
		$this->fixed_width = $value;
	}
	
	/**
	 * Returns the editor widget instance for this column 
	 * @return \exface\Core\Widgets\AbstractWidget
	 */
	public function get_editor() {
		return $this->editor;
	}
	
	/**
	 * Returns true if the column is editable and false otherwise
	 * @return boolean
	 */
	public function is_editable(){
		return $this->editable;
	}
	
	public function set_editor($uxon_object) {
		// TODO Fetch the default editor from data type. Probably need a editable attribute for the DataColumn,
		// wich would be the easiest way to set it editable and the editor would be optional then.
		$page = $this->get_page();
		$editor = WidgetFactory::create_from_uxon($page, UxonObject::from_anything($uxon_object), $this);
		if ($uxon_object->widget_type && $editor){
			$editor->set_attribute_alias($this->get_attribute_alias());
			$this->editor = $editor;
			$this->editable = true;
		} else {
			return false;
		}
	}
	
	public function get_align() {
		if (!$this->align){
			if ($this->get_data_type()->is(EXF_DATA_TYPE_NUMBER)
			|| $this->get_data_type()->is(EXF_DATA_TYPE_PRICE)
			|| $this->get_data_type()->is(EXF_DATA_TYPE_DATE)){
				$this->align = EXF_ALIGN_RIGHT;
			} elseif ($this->get_data_type()->is(EXF_DATA_TYPE_BOOLEAN)){
				$this->align = EXF_ALIGN_CENTER;
			} else {
				$this->align = EXF_ALIGN_LEFT;
			}
		} 
		return $this->align;
	}
	
	/**
	 * Returns the data type of the column as a constant (e.g. EXF_DATA_TYPE_NUMBER). The column's 
	 * data_type can either be set explicitly by UXON, or is derived from the shown meta attribute.
	 * If there is neither an attribute bound to the column, nor an explicit data_type EXF_DATA_TYPE_STRING
	 * is returned.
	 * 
	 * @return AbstractDataType
	 */
	public function get_data_type(){
		if ($this->data_type){
			return $this->data_type;
		} elseif ($attr = $this->get_attribute()){
			return $attr->get_data_type();
		} else {
			$exface = $this->exface();
			return DataTypeFactory::create_from_alias($exface, EXF_DATA_TYPE_STRING);
		}
	}
	
	public function set_data_type($exface_data_type){
		$this->data_type = $exface_data_type;
	}
	
	public function set_align($value) {
		$this->align = $value;
	}
	
	function get_attribute(){
		if ($attr = $this->get_meta_object()->get_attribute($this->get_attribute_alias())) {
			return $attr;
		} else {
			return false;
		}
	}
	
	public function get_aggregate_function() {
		return $this->aggregate_function;
	}
	
	public function set_aggregate_function($value) {
		$this->aggregate_function = $value;
	}
	
	public function get_include_in_quick_search() {
		return $this->include_in_quick_search;
	}
	
	public function set_include_in_quick_search($value) {
		$this->include_in_quick_search = $value;
	}

	public function get_children(){
		if ($this->is_editable() && $editor = $this->get_editor()){
			return array($editor);
		} else {
			return array();
		}
	}
	
	public function get_cell_styler_script() {
		return $this->cell_styler_script;
	}
	
	public function set_cell_styler_script($value) {
		$this->cell_styler_script = $value;
		return $this;
	}	
	
	public function get_size() {
		return $this->size;
	}
	
	public function set_size($value) {
		$this->size = $value;
		return $this;
	}
	
	public function get_style() {
		return $this->style;
	}
	
	public function set_style($value) {
		$this->style = $value;
		return $this;
	}  
	
	/**
	 * {@inheritDoc}
	 * By default the caption of a DataColumn will be set to the name of the displayed attribute or the name of the first attribute
	 * required for the formula (if the contents of the column is a formula).
	 * @see \exface\Core\Widgets\AbstractWidget::get_caption()
	 */
	public function get_caption(){
		if (!parent::get_caption()){
			if (!$attr = $this->get_attribute()){
				if ($this->get_expression()->is_formula()){
					$attr = $this->get_meta_object()->get_attribute($this->get_expression()->get_required_attributes()[0]);
				}
			}
			if ($attr){
				$this->set_caption($attr->get_name());
			}
		}
		return parent::get_caption();
	}
	
	/**
	 * @return Expression
	 */
	public function get_expression(){
		$exface = $this->exface();
		return ExpressionFactory::create_from_string($exface, $this->get_attribute_alias());
	}
	
	public function get_data_column_name() {
		if (is_null($this->data_column_name)){
			$this->data_column_name = \exface\Core\DataColumn::sanitize_column_name($this->get_attribute_alias());
		}
		return $this->data_column_name;
	}
	
	public function set_data_column_name($value) {
		$this->data_column_name = $value;
		return $this;
	}
	
	  
}
?>