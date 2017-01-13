<?php
namespace exface\Core\Widgets;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * The ChartAxis represents the X or Y axis of a chart.
 * 
 * Most important properties of a ChartAxis are it's caption, type (time, text, numbers, etc.), position and 
 * min/max values. An axis can also be hidden.
 * 
 * The ChartSeries widget can only be used within a Chart.
 * 
 * @author Andrej Kabachnik
 *
 */
class ChartAxis extends AbstractWidget {
	private $number = null;
	private $dimension = null;
	private $axis_type = null;
	private $data_column_id = null;
	private $min_value = null;
	private $max_value = null;
	private $position = null;
	
	const POSITION_TOP = 'TOP';
	const POSITION_RIGHT = 'RIGHT';
	const POSITION_BOTTOM = 'BOTTOM';
	const POSITION_LEFT = 'LEFT';
	
	const AXIS_TYPE_TIME = 'TIME';
	const AXIS_TYPE_TEXT = 'TEXT';
	const AXIS_TYPE_NUMBER = 'NUMBER';
	
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
	
	public function set_chart(Chart $widget) {
		$this->chart = $this->set_parent($widget);
	}
	
	/**
	 * Creates a chart series from the data of this axis. It's a shortcut instead of a full series definition.
	 * 
	 * @uxon-property chart_type
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return \exface\Core\Widgets\ChartAxis
	 */
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
	
	/**
	 * Specifies the data column to use for values of this axis by the column's id.
	 * 
	 * @uxon-property data_column_id
	 * @uxon-type string
	 * 
	 * @param string $value
	 */
	public function set_data_column_id($value) {
		$this->data_column_id = $value;
	}
	
	public function get_min_value() {
		return $this->min_value;
	}
	
	/**
	 * Sets the minimum value for the scale of this axis. If not set, the minimum value of the underlying data will be used.
	 * 
	 * @uxon-property min_value
	 * @uxon-type number
	 * 
	 * @param float $value
	 */
	public function set_min_value($value) {
		$this->min_value = $value;
	}
	
	public function get_max_value() {
		return $this->max_value;
	}
	
	/**
	 * Sets the maximum value for the scale of this axis. If not set, the maximum value of the underlying data will be used.
	 *
	 * @uxon-property max_value
	 * @uxon-type number
	 *
	 * @param float $value
	 */
	public function set_max_value($value) {
		$this->max_value = $value;
	}
	
	public function get_position() {
		return $this->position;
	}
	
	/**
	 * Defines the position of the axis on the chart: LEFT/RIGHT for Y-axes and TOP/BOTTOM for X-axes.
	 * 
	 * @uxon-property position
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return ChartAxis
	 */
	public function set_position($value) {
		$value = mb_strtoupper($value);
		if (defined('\\exface\\Core\\Widgets\\ChartAxis::POSITION_' . $value)){
			$this->position = $value;
		} else {
			throw new WidgetPropertyInvalidValueError($this, 'Invalid axis position "' . $value . '". Only TOP, RIGHT, BOTTOM or LEFT allowed!', '6TA2Y6A');
		}
		return $this;
	}
	
	public function get_axis_type() {
		return $this->axis_type;
	}
	
	/**
	 * Sets the type of the axis: TIME, TEXT or NUMBER.
	 * 
	 * @uxon-property axis_type
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return ChartAxis
	 */
	public function set_axis_type($value) {
		$value = mb_strtoupper($value);
		if (defined('\\exface\\Core\\Widgets\\ChartAxis::AXIS_TYPE_' . $value)){
			$this->axis_type = $value;
		} else {
			throw new WidgetPropertyInvalidValueError($this, 'Invalid axis type "' . $value . '". Only TIME, TEXT or NUMBER allowed!', '6TA2Y6A');
		}
		return $this;
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
	 * @see \exface\Core\Widgets\AbstractWidget::get_caption()
	 */
	public function get_caption(){
		if (is_null(parent::get_caption())){
			parent::set_caption($this->get_data_column()->get_caption());
		}
		return parent::get_caption();
	}
}
?>