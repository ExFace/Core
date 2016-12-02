<?php namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\UiWidgetConfigException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WidgetInterface;

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
	private $background_image_attribute_alias = null;
	private $scale = null;
	private $diagram_object_selector_widget = null;
	
	/**
	 * Returns an array of shapes usable in this diagram. Keep in mind, that these are not the actually plotted instances of
	 * shapes, but rather "types of shapes".
	 * @return DiagramShape[]
	 */
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
	
	public function get_background_image_attribute_alias() {
		return $this->background_image_attribute_alias;
	}
	
	public function set_background_image_attribute_alias($value) {
		$this->background_image_attribute_alias = $value;
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
	 
	public function get_diagram_object_selector_widget(){
		if (is_null($this->diagram_object_selector_widget)){
			$this->set_diagram_object_selector_widget($this->get_workbench()->create_uxon_object());
		}
		
		return $this->diagram_object_selector_widget;
	}
	
	public function set_diagram_object_selector_widget($widget_or_uxon){
		if ($widget_or_uxon instanceof \stdClass){
			//$this->diagram_object_selector_widget = $this->get_page()->create_widget('Filter', $this);
			/* @var $widget \exface\Core\Widgets\ComboTable */
			
			$widget_or_uxon->widget_type = $widget_or_uxon->widget_type ? $widget_or_uxon->widget_type : 'ComboTable';
			$widget = $this->get_page()->create_widget($widget_or_uxon->widget_type, $this, $widget_or_uxon);
			$widget->set_meta_object_id($this->get_meta_object()->get_id());
			$widget->set_attribute_alias($this->get_meta_object()->get_uid_alias());
			$widget->set_table_object_alias($this->get_meta_object()->get_alias_with_namespace());
			$widget->set_caption($this->get_meta_object()->get_name());
			$widget->set_disabled(false);
			//$this->diagram_object_selector_widget->set_widget($widget);
		} elseif ($widget_or_uxon instanceof WidgetInterface){
			$widget = $widget_or_uxon;
		} else {
			throw new UiWidgetConfigException('');
		}
		$this->diagram_object_selector_widget = $widget;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\Container::get_children()
	 */
	public function get_children(){
		return array_merge(parent::get_children(), array($this->get_diagram_object_selector_widget()));
	}
	
	public function prepare_data_sheet_to_prefill(DataSheetInterface $data_sheet = null){
		$data_sheet = parent::prepare_data_sheet_to_prefill($data_sheet);
		if ($data_sheet->get_meta_object()->is($this->get_meta_object())){
			
			if ($attr = $this->get_meta_object()->get_attribute($this->get_background_image_attribute_alias())){
				$data_sheet->get_columns()->add_from_attribute($attr);
			}
		}
		return $data_sheet;
	}

}

?>
