<?php namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Interfaces\Widgets\iCanBeRequired;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;

/**
 * A filter is a wrapper widget, which typically consist of one or more input widgets. The purpose of filters is to enable the user to
 * input conditions.
 * 
 * TODO Add an optional operator menu to the filter. That would be a drowdown populated with suitable comparison operators for the data
 * type of the value widget.
 * IDEA Should one filter also be able to create condition groups? Or should there be a FilterGroup widget?
 * 
 * @author Andrej Kabachnik
 *
 */
class Filter extends Container implements iCanBeRequired, iShowSingleAttribute {
	private $widget = null;
	private $comparator = null;
	private $required = null;
	
	/**
	 * Returns the widget used to interact with the filter (typically some kind of input widget)
	 * @return iTakeInput
	 */
	public function get_widget() {
		if (!$this->widget){
			$this->set_widget($this->get_page()->create_widget('Input', $this));
		}
		return $this->widget;
	}
	
	/**
	 * Sets the widget used to interact with the filter (typically some kind of input widget)
	 * @param iTakeInput||\stdClass $widget_or_uxon_object
	 * @return \exface\Core\Widgets\Filter
	 */
	public function set_widget($widget_or_uxon_object) {
		$page = $this->get_page();
		$this->widget = WidgetFactory::create_from_anything($page, $widget_or_uxon_object, $this);
		
		// Some widgets need to be transformed to be a meaningfull filter
		if ($this->widget->get_widget_type() == 'CheckBox'){
			$this->widget = $this->widget->transform_into_select();
		}
		
		// Set a default comparator
		if (is_null($this->get_comparator())){
			// If the input widget will produce multiple values, use the IN comparator
			if ($this->widget->implements_interface('iSupportMultiselect') && $this->widget->get_multi_select()){
				$this->set_comparator(EXF_COMPARATOR_IN);
			} 
			// Otherwise leave the comparator null for other parts of the logic to use their defaults
		}
		
		// If the filter has a specific comparator, that is non-intuitive, add a corresponding suffix to
		// the caption of the actual widget.
		switch ($this->get_comparator()){
			case EXF_COMPARATOR_GREATER_THAN:
			case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS:
			case EXF_COMPARATOR_LESS_THAN:
			case EXF_COMPARATOR_LESS_THAN_OR_EQUALS:
				$this->widget->set_caption($this->get_widget()->get_caption() . ' (' . $this->get_comparator() . ')');
				break;
		}
		
		// The widgets in the filter should not be required accept for the case if the filter itself is marked 
		// as required (see set_required()). This is important because, inputs based on required attributes are
		// marked required by default: this should not be the case for filters, however!
		if ($this->widget instanceof iCanBeRequired){
			$this->widget->set_required(false);
		}
		
		// Filters do not have default values, because they are empty if nothing has been entered. It is important
		// to tell the underlying widget to ignore defaults as it will use the default value of the meta attribute
		// otherwise. You can still set the value of the filter. This only prevents filling the value automatically
		// via the meta model defaults.
		if ($this->widget instanceof iHaveValue){
			$this->widget->set_ignore_default_value(true);
		}
		
		// The filter should be enabled all the time, except for the case, when it is diabled explicitly
		if (!parent::is_disabled()){
			$this->set_disabled(false);
		}
		
		return $this;
	}  
	
	/**
	 * @see \exface\Core\Widgets\Container::get_children()
	 */
	public function get_children(){
		return array($this->get_widget());
	}
	
	/**
	 * 
	 * @return Attribute
	 */
	public function get_attribute(){
		return $this->get_widget()->get_attribute();
	}
	
	/**
	 * 
	 * @return unknown
	 */
	public function get_attribute_alias(){
		return $this->get_widget()->get_attribute_alias();
	}
	
	/**
	 * 
	 * @return \exface\Core\Widgets\Filter
	 */
	public function set_attribute_alias($value){
		$this->get_widget()->set_attribute_alias($value);
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::get_value()
	 */
	public function get_value(){
		return $this->get_widget()->get_value();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::get_value_expression()
	 */
	public function get_value_expression(){
		return $this->get_widget()->get_value_expression();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::set_value()
	 */
	public function set_value($value){
		$this->get_widget()->set_value($value);
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::get_caption()
	 */
	public function get_caption(){
		return $this->get_widget()->get_caption();
	}
	
	
	/**
	 * Magic method to forward all calls to methods, not explicitly defined in the filter to ist value widget.
	 * Thus, the filter is a simple proxy from the point of view of the template. However, it can be easily
	 * enhanced with additional methods, that will override the ones of the value widget.
	 * TODO this did not really work so far. Don't know why. As a work around, added some explicit proxy methods
	 * @param string $name
	 * @param array $arguments
	 */
	public function __call($name, $arguments){
		$widget = $this->get_widget();
		return call_user_func_array(array($widget, $name), $arguments);
	}
	
	public function get_comparator() {
		return $this->comparator;
	}
	
	public function set_comparator($value) {
		if (!$value) return $this;
		$this->comparator = $value;
		return $this;
	} 
	
	public function is_required() {
		if (is_null($this->required)){
			return false;
		}
		return $this->required;
	}
	
	public function set_required($value) {
		$this->required = $value;
		if ($this->get_widget() && $this->get_widget() instanceof iCanBeRequired){
			$this->get_widget()->set_required($value);
		}
		return $this;
	}
	
	public function set_disabled($value) {
		if ($this->get_widget()){
			$this->get_widget()->set_disabled($value);
		}
		return parent::set_disabled($value);
	}  
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveValue::get_empty_text()
	 */
	public function get_empty_text(){
		return $this->get_widget()->get_empty_text();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveValue::set_empty_text()
	 */
	public function set_empty_text($value){
		$this->get_widget()->set_empty_text($value);
		return $this;
	}
	
	public function export_uxon_object(){
		$uxon = parent::export_uxon_object();
		$uxon->set_property('comparator', $this->get_comparator());
		$uxon->set_property('required', $this->is_required());
		$uxon->set_property('widget', $this->get_widget()->export_uxon_object());
		return $uxon;
	}
}
?>