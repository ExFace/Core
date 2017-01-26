<?php namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveBorders;
use exface\Core\Interfaces\Widgets\iShowDataSet;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * Shapes are what diagrams show: e.g. a blue rectangle. The diagram will show as many rectangles as rows in it's data subwidget.
 * 
 * In an editable diagram, there would typically be a toolbar with shapes, that can be dragged on the canvas. Each of them is a DiagramShape widget.
 * Dropping a shape onto the diagram would create an instance of it and fill that instance with data (title, attributes, etc.). The instances of
 * a shape are accessible via the data() subwidget. 
 * 
 * @author Andrej Kabachnik
 *
 */
class DiagramShape extends Form implements iShowDataSet, iHaveBorders {
	const SHAPE_TYPE_POLYGON = 'polygon';
	const SHAPE_CIRCLE = 'circle';
	const SHAPE_DONUT = 'donut';
	
	private $shape_options_attribute_alias = null;
	private $shape_caption_attribute_alias = null;
	private $shape_type = SHAPE_TYPE_POLYGON;
	private $coordinates = null;
	private $background_color = null;
	private $show_border = true;
	private $show_border_color = null;
	private $transparency = null;
	private $data = null;
	private $data_widget_link = null;
	
	public function get_shape_options_attribute_alias() {
		return $this->shape_options_attribute_alias;
	}
	
	public function set_shape_options_attribute_alias($value) {
		$this->shape_options_attribute_alias = $value;
		return $this;
	}
	
	public function get_shape_type() {
		return $this->shape_type;
	}
	
	public function set_shape_type($value) {
		$this->shape_type = $value;
		return $this;
	}
	
	public function get_background_color() {
		return $this->background_color;
	}
	
	public function set_background_color($value) {
		$this->background_color = $value;
		return $this;
	}
	
	public function get_transparency() {
		return $this->transparency;
	}
	
	public function set_transparency($value) {
		$this->transparency = $value;
		return $this;
	}  
	
	/**
	 * @return Data
	 */
	public function get_data() {
		if (is_null($this->data)){
			if ($link = $this->get_data_widget_link()){
				return $link->get_widget();
			} else {
				throw new WidgetConfigurationError($this, 'Cannot get data for ' . $this->get_widget_type() . ' "' . $this->get_id() . '": either data or data_widget_link must be defined in the UXON description!', '6T90WFX');
			}
		}
		return $this->data;
	}
	
	public function get_data_widget_link() {
		return $this->data_widget_link;
	}
	
	public function set_data_widget_link($value) {
		$exface = $this->get_workbench();
		$this->data_widget_link = WidgetLinkFactory::create_from_anything($exface, $value);
		return $this;
	}
	
	public function set_data(\stdClass $uxon_object) {
		// Force the data to be a DiagramShapeData widget
		$data = $this->get_page()->create_widget('DiagramShapeData', $this);
		unset($uxon_object->widget_type);
		// Import it's uxon definition
		$data->import_uxon_object($uxon_object);
		$this->data = $data;
	}
	
	public function get_coordinates() {
		return $this->coordinates;
	}
	
	public function set_coordinates($uxon_object) {
		$uxon = UxonObject::from_anything($uxon_object);
		$this->coordinates = $uxon;
		return $this;
	}  
	
	public function get_show_border() {
		return $this->show_border;
	}
	
	public function set_show_border($value) {
		$this->show_border = $value;
		return $this;
	}
	
	public function get_show_border_color() {
		return $this->show_border_color;
	}
	
	public function set_show_border_color($value) {
		$this->show_border_color = $value;
		return $this;
	}
	
	public function get_children(){
		return array_merge(parent::get_children(), array($this->get_data()));
	}
	
	/**
	 * 
	 * @return \exface\Core\CommonLogic\Model\Attribute
	 */
	public function get_shape_options_attribute(){
		return $this->get_meta_object()->get_attribute($this->get_shape_options_attribute_alias());
	}
	
	public function prepare_data_sheet_to_read(DataSheetInterface $data_sheet = null){
		$data_sheet = parent::prepare_data_sheet_to_read($data_sheet);
		if ($this->get_meta_object()->is($data_sheet->get_meta_object()) && $this->get_shape_options_attribute()){
			if ($this->get_shape_options_attribute_alias()){
				$data_sheet->get_columns()->add_from_attribute($this->get_shape_options_attribute());
			}
			if ($this->get_shape_caption_attribute_alias()){
				$data_sheet->get_columns()->add_from_attribute($this->get_shape_caption_attribute());
			}
			
		} else {
			// TODO
		}
		return $data_sheet;
	}
	
	/**
	 * 
	 * @return \exface\Core\CommonLogic\Model\RelationPath
	 */
	public function get_relation_path_to_diagram_object(){
		return $this->get_object_relation_path_to_parent();
	}
		
	/**
	 * 
	 * @return Diagram
	 */
	public function get_diagram(){
		return $this->get_parent();
	}
	
	public function get_shape_caption_attribute_alias() {
		return $this->shape_caption_attribute_alias;
	}
	
	/**
	 * 
	 * @return \exface\Core\CommonLogic\Model\Attribute
	 */
	public function get_shape_caption_attribute(){
		return $this->get_meta_object()->get_attribute($this->get_shape_caption_attribute_alias());
	}
	
	/**
	 * 
	 * @param string $value
	 * @return \exface\Core\Widgets\DiagramShape
	 */
	public function set_shape_caption_attribute_alias($value) {
		$this->shape_caption_attribute_alias = $value;
		return $this;
	}
}

?>
