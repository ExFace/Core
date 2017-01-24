<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * Dropdown menu to select from. Each menu item has a value and a text. One or more items can be selected.
 * Selects should be used for small data sets, as not all frameworks will support searching for values or 
 * lazy loading. If you have a large amount of data, use an InputCombo instead!
 *
 * @author Andrej Kabachnik
 */
class InputSelect extends Input implements iSupportMultiSelect { 
	private $value_text = '';
	private $multi_select = false;
	private $selectable_options = array();
	
	public function get_value_text() {
		return $this->value_text;
	}
	
	public function set_value_text($value) {
		$this->value_text = $value;
	}  	
	
	public function get_multi_select() {
		return $this->multi_select;
	}
	
	public function set_multi_select($value) {
		$this->multi_select = $value;
	} 
	
	public function get_selectable_options() {
		// If there are no selectable options set explicitly, try to determine them from the meta model. Otherwise the select box would be empty.
		if (empty($this->selectable_options) && $this->get_attribute()){
			if ($this->get_attribute()->get_data_type()->is(EXF_DATA_TYPE_BOOLEAN)){
				$this->set_selectable_options(array(1,0), array($this->translate('WIDGET.SELECT_YES'), $this->translate('WIDGET.SELECT_NO')));
			}
		}
		
		// Add unselected uption
		if (!$this->is_required() && !array_key_exists('', $this->selectable_options)){
			$options = array_merge(array('' => $this->translate('WIDGET.SELECT_NONE')), $this->selectable_options);
		} else {
			$options = $this->selectable_options;
		}
		return $options;
	}
	
	/**
	 * Sets the possible options of the select widget. Options can either be given as a assotiative array ([ key => label ]) or two
	 * separate arrays with equal length: one for keys and one for the labels.
	 * @param array|stdClass $array_or_object
	 * @param array $options_texts_array
	 * @throws WidgetPropertyInvalidValueError
	 */
	public function set_selectable_options($array_or_object, array $options_texts_array = NULL) {
		$options = array();
				
		// Add the specified options
		if ($array_or_object instanceof \stdClass){
			$options = array_merge($options, (array) $array_or_object);
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
				$options = array_merge($options, array_combine($array_or_object, $array_or_object));
			}
		} else {
			throw new WidgetPropertyInvalidValueError($this, 'Wrong data type for possible values of ' . $this->get_widget_type() . '! Expecting array or object, ' . gettype($array_or_object) . ' given.', '6T91S9G');
		}
		$this->selectable_options = $options;
		return $this;
	}
}
?>