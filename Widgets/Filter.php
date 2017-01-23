<?php namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Interfaces\Widgets\iCanBeRequired;
use exface\Core\Interfaces\Widgets\iHaveValue;

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
class Filter extends Container implements iCanBeRequired {
	private $widget = null;
	private $comparator = null;
	private $required = null;
	
	/**
	 * Returns the widget used to interact with the filter (typically some kind of input widget)
	 * @return \exface\Core\Widgets\AbstractWidget
	 */
	public function get_widget() {
		if (!$this->widget){
			$this->set_widget($this->get_page()->create_widget('Input', $this));
		}
		return $this->widget;
	}
	
	/**
	 * Sets the widget used to interact with the filter (typically some kind of input widget)
	 * @param \exface\widget\AbstractWidget || \stdClass $widget_or_uxon_object
	 * @return \exface\Core\Widgets\Filter
	 */
	public function set_widget($widget_or_uxon_object) {
		$page = $this->get_page();
		$this->widget = WidgetFactory::create_from_anything($page, $widget_or_uxon_object, $this);
		
		// Some widgets need to be transformed to be a meaningfull filter
		if ($this->widget->get_widget_type() == 'CheckBox'){
			$this->widget = $this->widget->transform_into_select();
		}
		
		// If the filter has a specific comparator, that is non-intuitive, add a corresponding suffix to
		// the caption of the actul widget.
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
	
	public function get_attribute_alias(){
		return $this->get_widget()->get_attribute_alias();
	}
	
	public function get_meta_object(){
		return $this->get_widget()->get_meta_object();
	}
	
	public function get_meta_object_id(){
		return $this->get_widget()->get_meta_object_id();
	}
	
	public function get_value(){
		return $this->get_widget()->get_value();
	}
	
	public function set_value($value){
		return $this->get_widget()->set_value($value);
	}
	
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
	  
}
?>