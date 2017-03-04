<?php namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\DataTypes\AbstractDataType;
use exface\Core\Interfaces\Widgets\iShowText;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Interfaces\WidgetInterface;

/**
 * The DataColumn represents a column in Data-widgets. The most common usecase are DataTable columns.
 * 
 * DataColumns are not always visible as columns. But they are always there, when tabular data is needed
 * for a widget. A DataColumn has a caption (header), an expression for it's contents (an attribute alias,
 * a formula, etc.) and an optional footer, where the contents can be summarized (e.g. summed up).
 * 
 * Many widgets support inline-editing. Their columns can be made editable by defining an editor widget
 * for the column. Any input widget (Inputs, Combos, etc.) can be used as an editor.
 * 
 * DataColumns can also be made sortable. This is usefull for template features like changing the sort
 * order via mouse click on the colum header.
 * 
 * @author Andrej Kabachnik 
 * 
 */
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
	
	public function has_footer(){
		if (!empty($this->footer)) return true;
		else return false;
	}
	
	public function get_attribute_alias(){
	  return $this->attribute_alias;
	}
	
	/**
	 * Makes the column display an attribute of the Data's meta object or a related object.
	 * 
	 * The attribute_alias can contain a relation path and/or an optional aggregator: e.g.
	 * "attribute_alias": "ORDER__POSITION__VALUE:SUM"
	 * 
	 * WARNING: This field currently also accepts formulas an string. However, this feature
	 * is not quite stable and it is not guaranteed for it to remain in future (it is more
	 * likely that formulas and widget links will be moved to a new generalized property of the
	 * DataColumn - presumabely "expression")
	 * 
	 * @uxon-property attribute_alias
	 * @uxon-type string
	 * 
	 * @param string $value
	 */
	public function set_attribute_alias($value) {
	  $this->attribute_alias = $value;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function get_sortable() {
		if (is_null($this->sortable)){
			if ($attr = $this->get_attribute()){
				$this->sortable = $attr->is_sortable();
			}
		}
	  	return $this->sortable;
	}
	
	/**
	 * Set to FALSE to disable sorting data via this column.
	 * 
	 * If the column represents a meta attribute, the sortable property of that attribute will be used.
	 * 
	 * @uxon-property sortable
	 * @uxon-type boolean
	 * 
	 * @param boolean
	 */
	public function set_sortable($value) {
	  $this->sortable = \exface\Core\DataTypes\BooleanDataType::parse($value);
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_footer() {
	  return $this->footer;
	}
	
	/**
	 * Makes the column display summary information in the footer. The value can be SUM, AVG, MIN, MAX, LIST and LIST_DISTINCT.
	 * 
	 * @uxon-property footer
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return DataColumn
	 */
	public function set_footer($value) {
	  $this->footer = $value;
	  return $this;
	} 

	public function get_fixed_width() {
		return $this->fixed_width;
	}
	
	public function set_fixed_width($value) {
		$this->fixed_width = $value;
	}
	
	/**
	 * Returns the editor widget instance for this column
	 * 
	 * @return WidgetInterface
	 */
	public function get_editor() {
		return $this->editor;
	}
	
	/**
	 * Returns TRUE if the column is editable and FALSE otherwise
	 * 
	 * @return boolean
	 */
	public function is_editable(){
		return $this->editable;
	}
	
	/**
	 * Defines an editor widget for the column making each row in it editable.
	 * 
	 * The editor is a UXON widget description object. Any input widget (Input, Combo, etc.)
	 * can be used. An editor can even be placed on non-attribute columns. This is very
	 * usefull if the action, that will receive the data, expects some input not related
	 * to the meta object.
	 * 
	 * Example:
	 *  {
	 *  	"attribute_alias": "MY_ATTRIBUTE",
	 *  	"editor": {
	 *  		"widget_type": "InputNumber"
	 *  	}
	 *  }
	 * 
	 * @uxon-property editor
	 * @uxon-type \exface\Core\Widgets\AbstractWidget
	 * 
	 * @param UxonObject $uxon_object
	 * @return boolean
	 */
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
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iCanBeAligned::get_align()
	 */
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
			$exface = $this->get_workbench();
			return DataTypeFactory::create_from_alias($exface, EXF_DATA_TYPE_STRING);
		}
	}
	
	public function set_data_type($exface_data_type){
		$this->data_type = $exface_data_type;
	}
	
	/**
	 * Sets the alignment of values in this column: LEFT, RIGHT or CENTER.
	 * 
	 * @uxon-property align
	 * @uxon-type string
	 * 
	 * @see \exface\Core\Interfaces\Widgets\iCanBeAligned::set_align()
	 */
	public function set_align($value) {
		$this->align = $value;
		return $this;
	}
	
	function get_attribute(){
		try {
			$attr = $this->get_meta_object()->get_attribute($this->get_attribute_alias());
			return $attr;
		} catch (MetaAttributeNotFoundError $e) {
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
	
	/**
	 * Set to TRUE to make the quick-search include this column (if the widget support quick search).
	 * 
	 * @uxon-property include_in_quick_search
	 * @uxon-type boolean
	 * 
	 * @param boolean $value
	 * @return DataColumn
	 */
	public function set_include_in_quick_search($value) {
		$this->include_in_quick_search = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}

	public function get_children(){
		if ($this->is_editable() && $editor = $this->get_editor()){
			return array($editor);
		} else {
			return array();
		}
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_cell_styler_script() {
		return $this->cell_styler_script;
	}
	
	/**
	 * Specifies a template-specific script to style the column: e.g. JavaScript for HTML-templates.
	 * 
	 * The exact effect of the cell_styler_script depends solemly on the implementation of the widget
	 * in the specific template.
	 * 
	 * @uxon-property cell_styler_script
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return \exface\Core\Widgets\DataColumn
	 */
	public function set_cell_styler_script($value) {
		$this->cell_styler_script = $value;
		return $this;
	}	
	
	public function get_size() {
		return $this->size;
	}
	
	/**
	 * Sets the font size for the values in this column: BIG, NORMAL or SMALL.
	 * 
	 * @uxon-property size
	 * @uxon-type string
	 * 
	 * @see \exface\Core\Interfaces\Widgets\iShowText::set_size()
	 */
	public function set_size($value) {
		$this->size = $value;
		return $this;
	}
	
	public function get_style() {
		return $this->style;
	}
	
	/**
	 * Sets the font style for the values in this column: NORMAL, BOLD, ITALIC, STRIKETHROUGH, UNDERLINE
	 * 
	 * @uxon-property style
	 * @uxon-type string
	 * 
	 * @see \exface\Core\Interfaces\Widgets\iShowText::set_style()
	 */
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
			try {
				$attr = $this->get_attribute();
			} catch (MetaAttributeNotFoundError $e){
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
		$exface = $this->get_workbench();
		return ExpressionFactory::create_from_string($exface, $this->get_attribute_alias());
	}
	
	public function get_data_column_name() {
		if (is_null($this->data_column_name)){
			$this->data_column_name = \exface\Core\CommonLogic\DataSheets\DataColumn::sanitize_column_name($this->get_attribute_alias());
		}
		return $this->data_column_name;
	}
	
	public function set_data_column_name($value) {
		$this->data_column_name = $value;
		return $this;
	}
	
	  
}
?>