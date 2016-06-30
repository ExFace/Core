<?php
namespace exface\Widgets;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\WidgetInterface;

/**
 * A filter is a wrapper widget, which typically consist of one or more input widgets. The purpose of filters is to enable the user to
 * input conditions.
 * TODO Add an optional operator menu to the filter. That would be a drowdown populated with suitable comparison operators for the data
 * type of the value widget.
 * IDEA Should one filter also be able to create condition groups? Or should there be a FilterGroup widget?
 * @author aka
 *
 */
class Filter extends Container {
	private $widget = null;
	private $comparator = null;
	
	/**
	 * Returns the widget used to interact with the filter (typically some kind of input widget)
	 * @return \exface\Widgets\AbstractWidget
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
	 * @return \exface\Widgets\Filter
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
		
		/*$this->set_id($this->widget->get_id());
		$this->widget->set_id($this->widget->get_id() . '_value');*/
		// The filter should be enabled all the time, except for the case, when it is diabled explicitly
		if (is_null($this->is_disabled())){
			$this->set_disabled(false);
		}
		
		return $this;
	}  
	
	/**
	 * @see \exface\Widgets\Container::get_children()
	 */
	public function get_children(){
		return array($this->get_widget());
	}
	
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
	
	public function set_disabled($value){
		// All parts of the filter have the same disabled state as the filter by default
		$this->widget->set_disabled($value);
		return parent::set_disabled($value);
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
}
?>