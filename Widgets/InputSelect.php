<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Factories\ConditionFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\DataSheets\DataSorter;
use exface\Core\Factories\DataSorterFactory;

/**
 * A dropdown menu to select from. Each menu item has a value and a text. Optional support for selecting multiple items.
 * 
 * The selectable options can either be specified directly (via the property "selectable_options") or generated from
 * the data source. In the latter case, attributes for text and values can be specified via text_attribute_alias and
 * value_attribute_alias. They do not need to have something to do with the object or attribute, that the widget
 * represents: the options are just values to pick from. Event a totally unrelated object can be specified to fetch
 * the options - via options_object_alias property. The selected value will then be saved to the attribute being
 * represented by the InputSelect itself.
 * 
 * Example 1 (manually defined options):
 * {
 *   "object_alias": "MY.APP.CUSTOMER",
 *   "widget_type": "InputSelect",
 *   "attribute_alias": "CLASSIFICATION",
 *   "selectable_options":
 *   [
 *   	"A": "A-Customer",
 *   	"B": "B-Customer",
 *   	"C": "C-Customer"
 *   ]
 * }
 * 
 * Example 2 (attributes of another object as options):
 * {
 *   "object_alias": "MY.APP.CUSTOMER",
 *   "widget_type": "InputSelect",
 *   "attribute_alias": "CLASSIFICATION",
 *   "options_object_alias": "MY.APP.CUSTOMER_CLASSIFICATION",
 *   "value_attribute_alias": "ID",
 *   "text_attribute_alias": "CLASSIFICATION_NAME"
 * }
 * 
 * By turning "use_prefill_to_filter_options" on or off, the prefill behavior can be customized. By default, the values 
 * from the prefill data will be used as options in the select automatically.
 *   
 * InputSelects should be used for small data sets, as not all frameworks will support searching for values or 
 * lazy loading. If you have a large amount of data, use an InputCombo instead!
 *
 * @author Andrej Kabachnik
 */
class InputSelect extends Input implements iSupportMultiSelect { 
	private $value_text = '';
	private $multi_select = false;
	private $selectable_options = array();
	private $text_attribute_alias = null;
	private $value_attribute_alias = null;
	private $custom_text_attribute_flag = false;
	private $options_object = null;
	private $options_object_alias = null;
	private $options_data_sheet = null;
	private $use_prefill_to_filter_options = true;
	private $use_prefill_values_as_options = false;
	
	/**
	 * 
	 * @return string
	 */
	public function get_value_text() {
		return $this->value_text;
	}
	
	/**
	 * Sets the text to be displayed for the current value (only makes sense if the "value" is set too!)
	 * 
	 * @uxon-property value_text
	 * @uxon-type string
	 * 
	 * @param string $value
	 */
	public function set_value_text($value) {
		$this->value_text = $value;
	}  	
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iSupportMultiSelect::get_multi_select()
	 */
	public function get_multi_select() {
		return $this->multi_select;
	}
	
	/**
	 * Set to TRUE to allow multiple items to be selected. FALSE by default.
	 * 
	 * @uxon-property multi-select
	 * @uxon-type boolean
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iSupportMultiSelect::set_multi_select()
	 */
	public function set_multi_select($value) {
		$this->multi_select = \exface\Core\DataTypes\BooleanDataType::parse($value);
	} 
	
	/**
	 * 
	 * @return array[]
	 */
	public function get_selectable_options() {
		// If there are no selectable options set explicitly, try to determine them from the meta model. Otherwise the select box would be empty.
		if (empty($this->selectable_options) && $this->get_attribute()){
			if ($this->get_attribute()->get_data_type()->is(EXF_DATA_TYPE_BOOLEAN)){
				$this->set_selectable_options(array(1,0), array($this->translate('WIDGET.SELECT_YES'), $this->translate('WIDGET.SELECT_NO')));
			}
		}
		
		// If there are no selectable options set explicitly, try to determine them from the meta model. Otherwise the select box would be empty.
		if (empty($this->selectable_options) && !$this->get_options_data_sheet()->is_blank()){
			$this->set_options_from_data_sheet($this->get_options_data_sheet());
		}
		
		// Add unselected uption
		if (!$this->is_required() 
		&& !array_key_exists('', $this->selectable_options)
		&& !($this->is_disabled() && $this->get_value())){
			$options = array_merge(array('' => $this->translate('WIDGET.SELECT_NONE')), $this->selectable_options);
		} else {
			$options = $this->selectable_options;
		}
		return $options;
	}
	
	/**
	 * Sets the possible options of the select widget via assotiative array or object: {"value1": "text1",  "value2": "text2"].
	 * 
	 * @uxon-property selectable_options
	 * @uxon-type Object
	 * 
	 * When adding options programmatically, separate arrays with equal length can be used: one for values and one for the text labels.
	 * 
	 * @param array|stdClass $array_or_object
	 * @param array $options_texts_array
	 * @throws WidgetPropertyInvalidValueError
	 * @return InputSelect
	 */
	public function set_selectable_options($array_or_object, array $options_texts_array = NULL) {
		$options = array();
				
		// Add the specified options
		if ($array_or_object instanceof \stdClass){
			$options = (array) $array_or_object;
		} elseif(is_array($array_or_object)) {
			if (is_array($options_texts_array)){
				if (count($array_or_object) != count($options_texts_array)){
					throw new WidgetPropertyInvalidValueError($this, 'Number of possible values (' . count($array_or_object) . ') differs from the number of keys (' . count($options_texts_array) . ') for widget "' . $this->get_id() . '"!', '6T91S9G');
				} else {
					foreach ($array_or_object as $nr => $id){
						$options[$id] = $options_texts_array[$nr];	
					}
				}
			} else {
				$options = array_combine($array_or_object, $array_or_object);
			}
		} else {
			throw new WidgetPropertyInvalidValueError($this, 'Wrong data type for possible values of ' . $this->get_widget_type() . '! Expecting array or object, ' . gettype($array_or_object) . ' given.', '6T91S9G');
		}
		$this->selectable_options = $options;
		return $this;
	}
	
	/**
	 * Returns the current number of selectable options
	 * 
	 * @return integer
	 */
	public function count_selectable_options(){
		$number = count($this->get_selectable_options());
		if (array_key_exists('', $this->get_selectable_options())){
			$number--;
		}
		return $number;
	}
	
	protected function do_prefill(DataSheetInterface $data_sheet){
		// First du the regular prefill for an input (setting the value)
		parent::do_prefill($data_sheet);
		// Additionally the InputSelect can use the prefill data to generate selectable options.
		// If the InputSelect is based on a meta attribute and does not have explicitly defined options, we can try to use
		// the prefill values to get the options. 
		if ($this->get_attribute() && !$this->count_selectable_options()){
			// If the prefill is based on the same object, just look for values of this attribute, add them as selectable options
			// and select all of them
			if ($data_sheet->get_meta_object()->is($this->get_meta_object()) && $col = $data_sheet->get_columns()->get_by_attribute($this->get_attribute())){
				$this->set_selectable_options($col->get_values(false));
				$this->set_values_from_array($col->get_values(false));
			}
			
			// Now see if the prefill object can be used to filter values
			if (!$this->get_use_prefill_values_as_options() && $this->get_use_prefill_to_filter_options()){
				// If the prefill object is the options object use prefill values to filter the options 
				if ($data_sheet->get_meta_object()->is($this->get_options_object())){
					// TODO which values from the prefill are we going to use here for fitlers? Which columns?
					// Or maybe use the filter of the prefill sheet? Or even ignore this case completely?
				}
				// If the prefill object is not the options object (or there was no special options object defined), but a 
				// relation to it can be found, use this relation as filter to query the data source for selectable options
				elseif ($rel = $this->get_options_object()->find_relation($data_sheet->get_meta_object(), true)) {
					if ($col = $data_sheet->get_columns()->get_by_expression($rel->get_related_object_key_alias())){
						$this->get_options_data_sheet()->add_filter_in_from_string($rel->get_alias(), $col->get_values(false));
					}
				}
			}
		}
	}
	
	protected function set_options_from_data_sheet(DataSheetInterface $data_sheet){
		$data_sheet->get_columns()->add_from_attribute($this->get_value_attribute());
		$data_sheet->get_columns()->add_from_attribute($this->get_text_attribute());
		$data_sheet->data_read();
		$this->set_selectable_options($data_sheet->get_columns()->get_by_attribute($this->get_value_attribute())->get_values(false), $data_sheet->get_columns()->get_by_attribute($this->get_text_attribute())->get_values(false));
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveValues::get_values()
	 */
	public function get_values(){
		if ($this->get_value()){
			return explode(EXF_LIST_SEPARATOR, $this->get_value());
		} else {
			return array();
		}
	}
	
	/**
	 * Defines multiple current values for the select via comma-separated list. To be used instead of "value" if "multi-select" is TRUE.
	 * 
	 * @uxon-property values
	 * @uxon-type string
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveValues::set_values()
	 */
	public function set_values($expression_or_delimited_list){
		$this->set_value($expression_or_delimited_list);
		return $this;
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
	
	/**
	 *
	 * @return \exface\Core\CommonLogic\Model\Attribute
	 */
	public function get_text_attribute(){
		return $this->get_options_object()->get_attribute($this->get_text_attribute_alias());
	}
	
	/**
	 * Defines the alias of the attribute to be displayed for every value. If not set, the system will try to determine one automatically.
	 * 
	 * If the text_attribute_alias was not set explicitly (e.g. via UXON), it will be determined as follows:
	 * - If an option object was specified explicitly, it's label will be used (or it's UID if no label is defined)
	 * - If the widget represents a relation, the related object's label will be used
	 * - If the widget represents the UID of it's object, than the label of this object will be used
	 * - If the widget represents any other attribute and there is no explicit options_object, this attribute
	 * will be used for values as well as for the displayed text.
	 *
	 * @uxon-property text_attribute_alias
	 * @uxon-type string
	 *
	 * @param string $value
	 * @return \exface\Core\Widgets\InputCombo
	 */
	public function set_text_attribute_alias($value) {
		$this->text_attribute_alias = $value;
		$this->custom_text_attribute_flag = true;
		return $this;
	}
	
	/**
	 * Returns the alias of the attribute to be displayed, when a value is selected.
	 * 
	 * If the text_attribute_alias was not set explicitly (e.g. via UXON), it will be determined as follows:
	 * - If an option object was specified explicitly, it's label will be used (or it's UID if no label is defined)
	 * - If the widget represents a relation, the related object's label will be used
	 * - If the widget represents the UID of it's object, than the label of this object will be used
	 * - If the widget represents any other attribute and there is no explicit options_object, this attribute
	 * will be used for values as well as for the displayed text.
	 * 
	 * @return string
	 */
	public function get_text_attribute_alias() {
		if (is_null($this->text_attribute_alias)){
			// If options are taken from the same object, than they are probably values of the referenced attribute,
			// unless it is a self-reference-relation (which should be treated just like a relation to other objects)
			// or the UID (which should get the label as text).
			if ($this->get_options_object()->is_exactly($this->get_meta_object())
			&& !($this->get_attribute() && ($this->get_attribute()->is_relation() || $this->get_attribute()->is_uid_for_object()))){
				$this->text_attribute_alias = $this->get_attribute_alias();
			} else {
				if ($this->get_options_object()->get_label_attribute()){
					$this->text_attribute_alias = $this->get_options_object()->get_label_alias();
				} else {
					$this->text_attribute_alias = $this->get_options_object()->get_uid_alias();
				}
			}
		}
		return $this->text_attribute_alias;
	}
	
	/**
	 * Returns TRUE if a text attribute was specified explicitly (e.g. via UXON-property "text_attribute_alias") and FALSE otherwise.
	 * @return boolean
	 */
	public function has_custom_text_attribute(){
		return $this->custom_text_attribute_flag;
	}
	
	/**
	 * Returns TRUE if the options object was specified explicitly (e.g. via UXON-property "options_object_alias") and FALSE otherwise.
	 * @return boolean
	 */
	public function has_custom_options_object(){
		return !($this->get_meta_object()->is_exactly($this->get_options_object()));
	}
	
	public function get_options_object(){
		if (is_null($this->options_object)){
			if (!$this->get_meta_object()->is_exactly($this->get_options_object_alias())){
				$this->options_object = $this->get_workbench()->model()->get_object($this->get_options_object_alias());
			} else {
				$this->options_object = $this->get_meta_object();
			}
		}
		return $this->options_object;
	}
	
	public function set_options_object(Object $value){
		$this->options_object = $value;
		return $this;
	}
	
	public function get_options_object_alias() {
		if (is_null($this->options_object_alias)){
			$this->options_object_alias = $this->get_meta_object()->get_alias_with_namespace();
		}
		return $this->options_object_alias;
	}
	
	/**
	 * Defines the meta object, which will be used to fetch the selectable options. By default it is the object of the widget itself.
	 * 
	 * @uxon-property options_object_alias
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return \exface\Core\Widgets\InputSelect
	 */
	public function set_options_object_alias($value) {
		$this->options_object_alias = $value;
		return $this;
	}  
	
	public function get_value_attribute_alias() {
		// If the not set explicitly, try to determine the value attribute automatically
		if (is_null($this->value_attribute_alias)){
			// If options are taken from the same object, than they are probably values of the referenced attribute,
			// unless it is a self-reference-relation, which should be treated just like a relation to other objects
			if ($this->get_options_object()->is_exactly($this->get_meta_object())
			&& !($this->get_attribute() && $this->get_attribute()->is_relation())){
				$this->value_attribute_alias = $this->get_attribute_alias();
			} else {
				$this->value_attribute_alias = $this->get_options_object()->get_uid_alias();
			}
		}
		return $this->value_attribute_alias;
	}
	
	public function get_value_attribute(){
		return $this->get_meta_object()->get_attribute($this->get_value_attribute_alias());
	}
	
	/**
	 * Defines the alias of the attribute to be used as the internal value of the select. If not set, the UID of the options object will be used
	 *
	 * @uxon-property value_attribute_alias
	 * @uxon-type string
	 *
	 * @param string $value
	 * @return InputSelect
	 */
	public function set_value_attribute_alias($value) {
		$this->value_attribute_alias = $value;
		return $this;
	}
	
	public function get_use_prefill_to_filter_options() {
		return $this->use_prefill_to_filter_options;
	}
	
	/**
	 * By default, the widget will try to only show options applicable to the prefill data. Set to FALSE to always show all options.
	 * 
	 * @uxon-property use_prefill_to_filter_options
	 * @uxon-type string
	 * 
	 * @param boolean $value
	 * @return \exface\Core\Widgets\InputSelect
	 */
	public function set_use_prefill_to_filter_options($value) {
		$this->use_prefill_to_filter_options = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}
	
	public function get_use_prefill_values_as_options() {
		return $this->use_prefill_values_as_options;
	}
	
	/**
	 * Makes the select only contain values from the prefill (if there are any) and no other options.
	 *
	 * @uxon-property use_prefill_to_filter_options
	 * @uxon-type string
	 *
	 * @param boolean $value
	 * @return \exface\Core\Widgets\InputSelect
	 */
	public function set_use_prefill_values_as_options($value) {
		$this->use_prefill_values_as_options = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}  
	
	public function get_options_data_sheet(){
		if (is_null($this->options_data_sheet)){
			$this->options_data_sheet = DataSheetFactory::create_from_object($this->get_options_object());
		}
		return $this->options_data_sheet;
	}
	
	public function set_options_data_sheet(DataSheetInterface $data_sheet){
		if (!$this->get_options_object()->is_exactly($data_sheet->get_meta_object())){
			throw new WidgetPropertyInvalidValueError($this, 'Cannot set options data sheet for ' . $this->get_widget_type() . ': meta object "' . $this->get_options_object()->get_alias_with_namespace() . '", but "' . $data_sheet->get_meta_object()->get_alias_with_namespace() . '" given instead!');
		}
		$this->options_data_sheet = $data_sheet;
		return $this;
	}
	
	/**
	 * Sets an optional array of filter-objects to be used when fetching selectable options from a data source. 
	 * 
	 * For example, if we have a select for values of attributes of a meta object, but we only wish to show
	 * values of active instances (assuming our object has the attribute "ACTIVE"), we would need the following
	 * select:
	 * {
	 * 	"options_object_alias": "my.app.myobject",
	 * 	"value_attribute_alias": "VALUE",
	 * 	"text_attribute_alias": "NAME",
	 * 	"filters":
	 * 	[
	 * 		{"attribute_alias": "ACTIVE", "value": "1", "comparator": "="}
	 * 	]
	 * }
	 * 
	 * @uxon-property filters
	 * @uxon-type \exface\Core\CommonLogic\Model\Condition
	 * 
	 * @param Condition[]|UxonObject[] $conditions_or_uxon_objects
	 * @return \exface\Core\Widgets\InputSelect
	 */
	public function set_filters(array $conditions_or_uxon_objects){
		foreach ($conditions_or_uxon_objects as $condition_or_uxon_object){
			if ($condition_or_uxon_object instanceof Condition){
				$this->get_options_data_sheet()->get_filters()->add_condition($condition_or_uxon_object);
			} elseif ($condition_or_uxon_object instanceof \stdClass) {
				$uxon = UxonObject::from_anything($condition_or_uxon_object);
				if (!$uxon->has_property('object_alias')){
					$uxon->set_property('object_alias', $this->get_meta_object()->get_alias_with_namespace());
				}
				$this->get_options_data_sheet()->get_filters()->add_condition(ConditionFactory::create_from_uxon($this->get_workbench(), $uxon));
			}
		}
		return $this;
	}
	
	/**
	 * Sets an optional array of sorter-objects to be used when fetching selectable options from a data source.
	 *
	 * For example, if we have a select for sizes of a product and we only wish to show sort the ascendingly,
	 * we would need the following config:
	 * {
	 * 	"options_object_alias": "my.app.product",
	 * 	"value_attribute_alias": "SIZE_ID",
	 * 	"text_attribute_alias": "SIZE_TEXT",
	 * 	"sorters":
	 * 	[
	 * 		{"attribute_alias": "SIZE_TEXT", "direction": "ASC"}
	 * 	]
	 * }
	 *
	 * @uxon-property sorters
	 * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSorter
	 *
	 * @param DataSorter[]|UxonObject[] $data_sorters_or_uxon_objects
	 * @return \exface\Core\Widgets\InputSelect
	 */
	public function set_sorters(array $data_sorters_or_uxon_objects){
		foreach ($data_sorters_or_uxon_objects as $sorter_or_uxon){
			if ($sorter_or_uxon instanceof DataSorter){
				$this->get_options_data_sheet()->get_sorters()->add($sorter_or_uxon);
			} elseif ($sorter_or_uxon instanceof \stdClass) {
				$uxon = UxonObject::from_anything($sorter_or_uxon);
				$this->get_options_data_sheet()->get_sorters()->add(DataSorterFactory::create_from_uxon($this->get_options_data_sheet(), $uxon));
			}
		}
		return $this;
	}
}
?>