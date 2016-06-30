<?php
namespace exface\Widgets;
class ChartAxis extends AbstractWidget {
	private $number = null;
	private $dimension = null;
	private $axis_type = null;
	private $data_column_id = null;
	private $min_value = null;
	private $max_value = null;
	private $position = null;
	
	const POSITION_TOP = 'top';
	const POSITION_RIGHT = 'right';
	const POSITION_BOTTOM = 'bottom';
	const POSITION_LEFT = 'left';
	
	const AXIS_TYPE_TIME = 'time';
	const AXIS_TYPE_TEXT = 'text';
	const AXIS_TYPE_NUMBER = 'number';
	
	/**
	 * @return DataColumn
	 */
	public function get_data_column(){
		if (!$result = $this->get_chart()->get_data()->get_column($this->get_data_column_id())){
			$result = $this->get_chart()->get_data()->get_column_by_attribute_alias($this->get_data_column_id());
		}
		return $result;
	}
	
	/**
	 * @return Chart
	 */
	public function get_chart() {
		return $this->get_parent();
	}
	
	public function set_chart(Chart &$widget) {
		$this->chart = $this->set_parent($widget);
	}
	
	public function set_chart_type($value) {
		$series = $this->get_chart()->create_series($value);
		switch ($value){
			case ChartSeries::CHART_TYPE_BARS : $series->set_axis_x($this); break;
			default: $series->set_axis_y($this);  
		}
		$series->set_data_column_id($this->get_data_column_id());
		$this->get_chart()->add_series($series);
		return $this;
	}
	
	public function get_data_column_id() {
		return $this->data_column_id;
	}
	
	public function set_data_column_id($value) {
		$this->data_column_id = $value;
	}
	
	public function get_min_value() {
		return $this->min_value;
	}
	
	public function set_min_value($value) {
		$this->min_value = $value;
	}
	
	public function get_max_value() {
		return $this->max_value;
	}
	
	public function set_max_value($value) {
		$this->max_value = $value;
	}
	
	public function get_position() {
		return $this->position;
	}
	
	public function set_position($value) {
		$this->position = $value;
	}
	
	public function get_axes_type() {
		return $this->axis_type;
	}
	
	public function set_axis_type($value) {
		$this->axis_type = $value;
	} 
	
	public function get_dimension() {
		return $this->dimension;
	}
	
	public function set_dimension($x_or_y) {
		$this->dimension = $x_or_y;
		return $this;
	}  
	
	public function get_number() {
		return $this->number;
	}
	
	public function set_number($value) {
		$this->number = $value;
		return $this;
	}  
	
	/**
	 * The caption for an axis can either be set directly, or will be inherited from the used data column
	 * {@inheritDoc}
	 * @see \exface\Widgets\AbstractWidget::get_caption()
	 */
	public function get_caption(){
		if (is_null(parent::get_caption())){
			parent::set_caption($this->get_data_column()->get_caption());
		}
		return parent::get_caption();
	}
}
?>