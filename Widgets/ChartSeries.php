<?php namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * The ChartSeries represents a single series in a chart (e.g. line of a line chart). 
 * 
 * Most important options of ChartSeries are the chart type (line, bars, columns, etc.) and the data_column_id to fetch the values from.
 * 
 * For simple charts, you do not need to specify each series separately - simply add the desired "chart_type" to the axis
 * with the corresponding data_column_id.
 * 
 * The ChartSeries widget can only be used within a Chart.
 * 
 * @author Andrej Kabachnik
 *
 */
class ChartSeries extends AbstractWidget {
	const CHART_TYPE_LINE = 'line';
	const CHART_TYPE_BARS = 'bars';
	const CHART_TYPE_COLUMNS = 'columns';
	const CHART_TYPE_AREA = 'area';
	const CHART_TYPE_PIE = 'pie';
	
	private $chart_type = null;
	private $series_number = null;
	private $data_column_id = null;
	private $axis_x_number = null;
	private $axis_x = null;
	private $axis_y_number = null;
	private $axis_y = null;
	
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
		return $this;
	}
	
	public function get_chart_type() {
		return $this->chart_type;
	}
	
	/**
	 * Sets the visualization type for this series: line, bars, columns, pie or area.
	 * 
	 * @uxon-property chart_type
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return \exface\Core\Widgets\ChartSeries
	 */
	public function set_chart_type($value) {
		$this->chart_type = strtolower($value);
		return $this;
	}
	
	public function get_data_column_id() {
		return $this->data_column_id;
	}
	
	/**
	 * Defines the column in the chart's data, that will provide the values of this series.
	 * 
	 * @uxon-property data_column_id
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return ChartSeries
	 */
	public function set_data_column_id($value) {
		$this->data_column_id = $value;
		return $this;
	} 
	
	public function get_axis_x(){
		if (is_null($this->axis_x)){
			$axis = $this->get_chart()->find_axis_by_column_id($this->get_data_column_id(), Chart::AXIS_X);
			if (!$axis){
				$axis = $this->get_chart()->get_axes_x()[0];
			}
			if (!$axis){
				throw new WidgetConfigurationError($this, 'Cannot find x-axis for series "' . $this->get_id() . '" of widget "' . $this->get_chart()->get_id() . '"!', '6T90UV9');
			}
			$this->axis_x = $axis;
		}
		return $this->axis_x;
	}
	
	public function set_axis_x(ChartAxis $axis){
		$this->axis_x = $axis;
	}
	
	public function get_axis_x_number() {
		if (is_null($this->axis_x_number) && $this->get_axis_x()){
			return $this->get_axis_x()->get_number();
		}
		return $this->axis_x_number;
	}
	
	/**
	 * Makes the series use the specified X-axis: e.g. axis_x_number = 2 will make the X-values appear on the second X-axis.
	 * 
	 * @uxon-property axis_x_number
	 * @uxon-type string
	 * 
	 * @param integer $number
	 * @return \exface\Core\Widgets\ChartSeries
	 */
	public function set_axis_x_number($number) {
		$this->axis_x_number = $number;
		return $this;
	}
	
	public function get_axis_y(){
		if (is_null($this->axis_y)){
			$axis = $this->get_chart()->find_axis_by_column_id($this->get_data_column_id(), Chart::AXIS_Y);
			if (!$axis){
				$axis = $this->get_chart()->get_axes_y()[0];
			}
			if (!$axis){
				throw new WidgetConfigurationError($this, 'Cannot find y-axis for series "' . $this->get_chart_type() . '" of widget "' . $this->get_chart()->get_id() . '"!', '6T90UV9');
			}
			$this->axis_y = $axis;
		}
		return $this->axis_y;
	}
	
	public function set_axis_y(ChartAxis $axis){
		$this->axis_y = $axis;
		return $this;
	}
	
	public function get_axis_y_number() {
		if (is_null($this->axis_y_number) && $this->get_axis_y()){
			return $this->get_axes_y()->get_id();
		}
		return $this->axis_y_number;
	}
	
	/**
	 * Makes the series use the specified Y-axis: e.g. axis_x_number = 2 will make the Y-values appear on the second Y-axis.
	 *
	 * @uxon-property axis_y_number
	 * @uxon-type string
	 *
	 * @param integer $number
	 * @return \exface\Core\Widgets\ChartSeries
	 */
	public function set_axis_y_number($number) {
		$this->axis_y_number = $number;
		return $this;
	}
	
	/**
	 * The caption for a series can either be set directly, or will be inherited from the used data column.
	 * 
	 * @uxon-property caption
	 * @uxon-type string
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::get_caption()
	 */
	public function get_caption(){
		if (is_null(parent::get_caption())){
			parent::set_caption($this->get_data_column()->get_caption());
		}
		return parent::get_caption();
	}
	
	public function get_series_number() {
		if (is_null($this->series_number)){
			foreach ($this->get_chart()->get_series() as $n => $s){
				if ($s->get_id() == $this->get_id()){
					$this->series_number = $n;
				}
			}
		}
		return $this->series_number;
	}
	
	public function set_series_number($value) {
		$this->series_number = $value;
		return $this;
	}  
}
?>