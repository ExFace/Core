<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

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
	
	/**
	 * 
	 * @return string
	 */
	public function get_value_text() {
		return $this->value_text;
	}
	
	/**
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
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iSupportMultiSelect::set_multi_select()
	 */
	public function set_multi_select($value) {
		$this->multi_select = $value ? true : false;
	} 
	
	public function get_selectable_options() {
		// If there are no selectable options set explicitly, try to determine them from the meta model. Otherwise the select box would be empty.
		if (empty($this->selectable_options) && $this->get_attribute()){
			if ($this->get_attribute()->get_data_type()->is(EXF_DATA_TYPE_BOOLEAN)){
				$this->set_selectable_options(array(1,0), array($this->translate('WIDGET.SELECT_YES'), $this->translate('WIDGET.SELECT_NO')));
			}
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
		parent::do_prefill($data_sheet);
		if ($this->get_attribute() && !$this->count_selectable_options()){
			if ($data_sheet->get_meta_object()->is($this->get_meta_object()) && $col = $data_sheet->get_columns()->get_by_attribute($this->get_attribute())){
				$this->set_selectable_options($col->get_values(false));
				$this->set_values_from_array($col->get_values(false));
			}
		}
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
}
?>