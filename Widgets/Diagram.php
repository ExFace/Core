<?php namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\UiWidgetConfigException;

/**
 * Widget to display diagrams like planograms, entity-relationships, organigrams, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
class Diagram extends Container implements iSupportLazyLoading {
	private $lazy_loading = false; // A diagram will not be loaded via AJAX by default
	private $lazy_loading_action = 'exface.Core.ReadData';
	private $diagram_options_attribute_alias = null;
	private $background_image = null;
	private $scale = null;
	private $object_filter_widget = null;
	
	public function get_shapes() {
		return $this->get_widgets();
	}
	
	public function set_shapes($array_of_uxon_or_widgets) {
		$shapes = array();
		foreach ($array_of_uxon_or_widgets as $shape){
			if ($shape instanceof \stdClass){
				$uxon = UxonObject::from_anything($shape);
				if (!$uxon->get_property('widget_type')){
					$uxon->set_property('widget_type', 'DiagramShape');
				}
				$shapes[] = $uxon;
			} elseif ($shape instanceof DiagramShape) {
				$shapes[] = $shape;
			} else {
				throw new UiWidgetConfigException('Wrong data type for diagram shape: Shapes must be defined as UXON objects or widgets of type DiagramShape: "' . get_class($shape) . '" given!');
			}
		}
		$this->set_widgets($shapes);
		return $this;
	}
	
	public function get_diagram_options_attribute_alias() {
		return $this->diagram_options_attribute_alias;
	}
	
	public function set_diagram_options_attribute_alias($value) {
		$this->diagram_options_attribute_alias = $value;
		return $this;
	}
	
	public function get_background_image() {
		return $this->background_image;
	}
	
	public function set_background_image($value) {
		$this->background_image = $value;
		return $this;
	}
	
	/**
	 * 
	 * @return array ["width" => XX, "height" => YY, "unit" => "cm"]
	 */
	public function get_scale() {
		return $this->scale;
	}
	
	public function set_scale($value) {
		$this->scale = $value;
		return $this;
	}    
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::get_lazy_loading()
	 */
	public function get_lazy_loading() {
		return $this->lazy_loading;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::set_lazy_loading()
	 */
	public function set_lazy_loading($value) {
		$this->lazy_loading = $value;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::get_lazy_loading_action()
	 */
	public function get_lazy_loading_action() {
		return $this->lazy_loading_action;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::set_lazy_loading_action()
	 */
	public function set_lazy_loading_action($value) {
		$this->lazy_loading_action = $value;
		return $this;
	}
	 
	public function get_object_filter_widget(){
		if (is_null($this->object_filter_widget)){
			//$this->object_filter_widget = $this->get_page()->create_widget('Filter', $this);
			/* @var $widget \exface\Core\Widgets\ComboTable */
			$widget = $this->get_page()->create_widget('ComboTable', $this);
			$widget->set_meta_object_id($this->get_meta_object()->get_id());
			$widget->set_attribute_alias($this->get_meta_object()->get_uid_alias());
			$widget->set_table_object_alias($this->get_meta_object()->get_alias_with_namespace());
			$widget->set_caption($this->get_meta_object()->get_name());
			$widget->set_disabled(false);
			//$this->object_filter_widget->set_widget($widget);
			$this->object_filter_widget = $widget;
		}
		
		return $this->object_filter_widget;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\Container::get_children()
	 */
	public function get_children(){
		return array_merge(parent::get_children(), array($this->get_object_filter_widget()));
	}
}

?>
