<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Interfaces\Widgets\iHaveDefaultValue;

class Input extends Text implements iTakeInput, iHaveDefaultValue {
	private $required = null;
	private $validator = null;
	private $readonly = false;
	
	public function get_validator() {
		return $this->validator;
	}
	
	public function set_validator($value) {
		$this->validator = $value;
	}
	
	/**
	 * {@inheritDoc}
	 * Input widgets are considered as required if they are explicitly marked as such or if the represent a meta attribute, 
	 * that is a required one.
	 * 
	 * IDEA It's not quite clear, if automatically marking an input as required depending on it's attribute being required,
	 * is a good idea. This works well for forms creating objects, but what if the form is used for something else? If there
	 * will be problems with this feature, the alternative would be making the EditObjectAction loop through it's widgets
	 * and set the required flag depending on attribute setting.
	 * 
	 * @see \exface\Core\Interfaces\Widgets\iTakeInput::is_required()
	 */
	public function is_required() {
		if (is_null($this->required)){
			if ($this->get_attribute()){
				return $this->get_attribute()->is_required();
			} else {
				return false;
			}
		}
		return $this->required;
	}
	
	public function set_required($value) {
		$this->required = $value;
	}
	
	/**
	 * Input widgets are disabled if the displayed attribute is not editable or if the widget was explicitly disabled.
	 * @see \exface\Core\Widgets\AbstractWidget::is_disabled()
	 */
	public function is_disabled(){
		if ($this->is_readonly()){
			return true;
		}
		
		$disabled = parent::is_disabled();
		if (is_null($disabled)){
			try {
				if (!$this->get_attribute()->is_editable()){
					$disabled = true;
				} else {
					$disabled = false;
				}
			} catch (MetaAttributeNotFoundError $e){
				// Ignore invalid attributes
			}
		}
		return $disabled;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iTakeInput::is_readonly()
	 */
	public function is_readonly() {
		return $this->readonly;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iTakeInput::set_readonly()
	 */
	public function set_readonly($value) {
		$this->readonly = $value ? true : false;
		return $this;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveValue::get_default_value()
	 */
	public function get_default_value(){
		if (!$this->get_ignore_default_value() && $default_expr = $this->get_default_value_expression()){
			if ($data_sheet = $this->get_prefill_data()){
				$value = $default_expr->evaluate($data_sheet, \exface\Core\CommonLogic\DataSheets\DataColumn::sanitize_column_name($this->get_attribute()->get_alias()), 0);
			} elseif ($default_expr->is_string()){
				$value = $default_expr->get_raw_value();
			}
		}
		return $value;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveValue::get_default_value_expression()
	 */
	public function get_default_value_expression(){
		if ($attr = $this->get_attribute()){
			if (!$default_expr = $attr->get_fixed_value()){
				$default_expr = $attr->get_default_value();
			}
		}
		return $default_expr;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveValue::get_ignore_default_value()
	 */
	public function get_ignore_default_value() {
		return $this->ignore_default_value;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveValue::set_ignore_default_value()
	 */
	public function set_ignore_default_value($value) {
		$this->ignore_default_value = $value ? true : false;
		return $this;
	}
	
	/**
	 * Inputs have a separate default placeholder value (mostly none). Placeholders should be specified manually for each
	 * widget to give the user a helpful hint.
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\Text::get_empty_text()
	 */
	public function get_empty_text(){
		if (parent::get_empty_text() == $this->translate('WIDGET.TEXT.EMPTY_TEXT')){
			parent::set_empty_text($this->translate('WIDGET.INPUT.EMPTY_TEXT'));
		}
		return parent::get_empty_text();
	}
  
}
?>