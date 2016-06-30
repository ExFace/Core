<?php
namespace exface\Widgets;
use exface\Core\Exceptions\UiWidgetConfigException;
use exface\Core\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveTopToolbar;
use exface\Core\Interfaces\Widgets\iHaveBottomToolbar;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\WidgetLink;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;

class Chart extends AbstractWidget implements iHaveButtons, iHaveTopToolbar, iHaveBottomToolbar, iSupportLazyLoading {
	/**
	 * @uxon axes_x Array of x-axis definitions. At least one axis must be provided!
	 * @var ChartAxis[]
	 */
	private $axes_x = array();
	/**
	 * @uxon axes_y Array of y-axis definitions. At least one axis must be provided!
	 * @var ChartAxis[]
	 */
	private $axes_y = array();
	/**
	 * @uxon series Array of series descriptions.
	 * @var ChartSeries[] $series
	 */
	private $series = array();
	/**
	 * @uxon data Data widget (simple table with data), wich will be the source for the chart. Chart axes and series will be
	 * bound to columns of this data widget. In the simplest case, there should be a coulum with x-axis-values and one with
	 * y-axis-values. The series property is optional, as you can also add a chart_type property to any axis to have get
	 * an automatically generated series for values of that axis. 
	 * @var Data
	 */
	private $data = null;
	/**
	 * @uxon data_widget_link If a valid link to another data widget is specified, it's data will be used instead of the data property of the chart itself.
	 * This is very handy if you want to visualize the data presented by a table or so. Using the link will make the chart automatically react to filters
	 * and other setting of the target data widget.
	 * @var UxonObject || string
	 */
	private $data_widget_link = null;
	/**
	 * @uxon stack_series Set to true to stack all series of this chart
	 * @var boolean
	 */
	private $stack_series = false;
	/**
	 * @uxon hide_toolbar_top Set to true to hide the top toolbar, which generally will contain filters and other settings
	 * @var boolean
	 */
	private $hide_toolbar_top = false;
	/**
	 * @uxon hide_toolbar_bottom Set to true to hide the bottom toolbar, which generally will contain pagination
	 * @var boolean
	 */
	private $hide_toolbar_bottom = false;

	/** @var Button[] */
	private $buttons = array();
	
	const AXIS_X = 'x';
	const AXIS_Y = 'y';
	
	public function get_children(){
		$children = array();
		if (!$this->get_data_widget_link()){
			$children[] = $this->get_data();
		}
		$children = array_merge($children, $this->get_axes(), $this->get_series());
		return $children;
	}
	
	/**
	 * @return ChartAxis[]
	 */
	public function get_axes_x() {
		return $this->axes_x;
	}
	
	public function set_axis_x($axis_or_uxon_object_or_array) {
		if ($axis_or_uxon_object_or_array instanceof ChartAxis){
			$this->add_axis('x', $axis_or_uxon_object_or_array);
		} elseif (is_array($axis_or_uxon_object_or_array)) {
			foreach ($axis_or_uxon_object_or_array as $axis){
				$this->set_axis_x($axis);
			}
		} else {
			$axis = $this->get_page()->create_widget('ChartAxis', $this);
			$axis->import_uxon_object($axis_or_uxon_object_or_array);
			$this->add_axis('x', $axis);
		}
		return $this;
	}
	
	/**
	 * 
	 * @return ChartAxis[]
	 */
	public function get_axes_y() {
		return $this->axes_y;
	}
	
	/**
	 * @return ChartAxis[]
	 */
	public function get_axes($x_or_y = null){
		switch ($x_or_y){
			case $this::AXIS_X:	return $this->get_axes_x(); 
			case $this::AXIS_Y: return $this->get_axes_y();
			default: return array_merge($this->get_axes_x(), $this->get_axes_y());
		}
	}
	
	public function set_axis_y($axis_or_uxon_object_or_array) {
		if ($axis_or_uxon_object_or_array instanceof ChartAxis){
			$this->add_axis('y', $axis_or_uxon_object_or_array);
		} elseif (is_array($axis_or_uxon_object_or_array)) {
			foreach ($axis_or_uxon_object_or_array as $axis){
				$this->set_axis_y($axis);
			}
		} else {
			$axis = $this->get_page()->create_widget('ChartAxis', $this);
			$axis->import_uxon_object($axis_or_uxon_object_or_array);
			$this->add_axis('y', $axis);
		}
		return $this;
	}
	
	public function add_axis($x_or_y, ChartAxis &$axis){
		$axis->set_chart($this);
		$axis->set_dimension($x_or_y);
		if (!$axis->get_position()){
			switch ($x_or_y){
				case $this::AXIS_Y: $axis->set_position(ChartAxis::POSITION_LEFT); break;
				case $this::AXIS_X: $axis->set_position(ChartAxis::POSITION_BOTTOM); break;
				default: throw new UiWidgetConfigException('Invalid axis coordinate: "' . $x_or_y . '"! "x" or "y" expected!');
			}
		}
		$var = 'axes_' . $x_or_y;
		$count = array_push($this->$var, $axis);
		$axis->set_number($count);
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
				throw new UiWidgetConfigException('Cannot get data for ' . $this->get_widget_type() . ' "' . $this->get_id() . '": either data or data_widget_link must be defined in the UXON description!');
			}
		}
		return $this->data;
	}
	
	public function set_data(\stdClass $uxon_object) {
		$data = $this->get_page()->create_widget('Data', $this);
		$data->set_meta_object_id($this->get_meta_object_id());
		$data->import_uxon_object($uxon_object);
		$this->data = $data;
	}
	
	/**
	 * 
	 * @param string $column_id
	 * @param string $x_or_y
	 * @return ChartAxis | boolean
	 */
	public function find_axis_by_column_id($column_id, $x_or_y = null){
		foreach ($this->get_axes($x_or_y) as $axis){
			if ($axis->get_data_column_id() == $column_id){
				return $axis;
			}
		}
		return false;
	}
	
	/**
	 *
	 * @param string $alias_with_relation_path
	 * @param string $x_or_y
	 * @return ChartAxis | boolean
	 */
	public function find_axis_by_attribute_alias($alias_with_relation_path, $x_or_y = null){
		foreach ($this->get_axes($x_or_y) as $axis){
			if ($axis->get_data_column()->get_attribute() 
			&& $axis->get_data_column()->get_attribute()->get_alias_with_relation_path() == $alias_with_relation_path){
				return $axis;
			}
		}
		return false;
	}
	
	/**
	 * @return ChartSeries[]
	 */
	public function get_series() {
		return $this->series;
	}
	
	/**
	 * @param string $chart_type
	 * @return ChartSeries[]
	 */
	public function get_series_by_chart_type($chart_type){
		$result = array();
		foreach ($this->get_series() as $series){
			if ($series->get_chart_type() === $chart_type){
				$result[] = $series;
			}
		}
		return $result;
	}
	
	public function set_series($series_or_uxon_object_or_array) {
		if ($series_or_uxon_object_or_array instanceof ChartAxis){
			$this->add_series($series_or_uxon_object_or_array);
		} elseif (is_array($series_or_uxon_object_or_array)) {
			foreach ($series_or_uxon_object_or_array as $series){
				$this->set_series($series);
			}
		} else {
			$series = $this->create_series(null, $series_or_uxon_object_or_array);
			$this->add_series($series);
		}
		return $this;
	} 
	
	/**
	 * 
	 * @param string $chart_type
	 * @param \stdClass $uxon
	 * @return ChartSeries
	 */
	public function create_series($chart_type = null, \stdClass $uxon = null){ 
		$series = $this->get_page()->create_widget('ChartSeries', $this);
		if ($uxon){
			$series->import_uxon_object($uxon);
		}
		if (!is_null($chart_type)){
			$series->set_chart_type($chart_type);
		}
		return $series;
	}
	
	public function add_series(ChartSeries &$series){
		$series->set_chart($this);
		$this->series[] = $series;
		return $this;
	}
	
	/**
	 * 
	 * @return WidgetLink
	 */
	public function get_data_widget_link() {
		return $this->data_widget_link;
	}
	
	public function set_data_widget_link($value) {
		$exface = $this->exface();
		$this->data_widget_link = WidgetLinkFactory::create_from_anything($exface, $value);
		return $this;
	}  
	
	public function get_stack_series() {
		return $this->stack_series;
	}
	
	public function set_stack_series($value) {
		$this->stack_series = $value;
		return $this;
	}  
	
	public function set_hide_axes($boolean){
		if ($boolean){
			foreach ($this->get_axes() as $axis){
				$axis->set_hidden(true);
			}
		}
		return $this;
	}
	
	public function get_width(){
		if (!$this->width){
			$this->set_width('max');
		}
		return parent::get_width();
	}
	
	public function get_hide_toolbar_top() {
		return $this->hide_toolbar_top;
	}
	
	public function set_hide_toolbar_top($value) {
		$this->hide_toolbar_top = $value;
		return $this;
	}
	
	public function get_hide_toolbar_bottom() {
		return $this->hide_toolbar_bottom;
	}
	
	public function set_hide_toolbar_bottom($value) {
		$this->hide_toolbar_bottom = $value;
		return $this;
	}
	
	public function get_hide_toolbars() {
		return ($this->get_hide_toolbar_top() && $this->get_hide_toolbar_bottom());
	}
	
	public function set_hide_toolbars($value) {
		$this->set_hide_toolbar_top($value);
		$this->set_hide_toolbar_bottom($value);
		return $this;
	}
	
	/**
	 * {@inheritDoc}
	 * 
	 * A Chart can be prefilled just like all the other data widgets, but only if it has it's own data. If the data is fetched from
	 * a linked widget, the prefill does not make sense and will be ignored. But the linked widget will surely be prefilled, so the
	 * the chart will get the correct data anyway.
	 * 
	 * @see \exface\Widgets\Data::prefill()
	 */
	public function prefill(DataSheetInterface $data_sheet){
		if ($this->get_data_widget_link()){
			return parent::prefill($data_sheet);
		} else {
			return $this->get_data()->prefill($data_sheet);
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Widgets\AbstractWidget::prepare_data_sheet_to_prefill()
	 */
	public function prepare_data_sheet_to_prefill(DataSheetInterface $data_sheet = null){
		return $this->get_data()->prepare_data_sheet_to_prefill($data_sheet);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Widgets\AbstractWidget::prepare_data_sheet_to_read()
	 */
	public function prepare_data_sheet_to_read(DataSheetInterface $data_sheet = null){
		return $this->get_data()->prepare_data_sheet_to_read($data_sheet);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::get_buttons()
	 * @return DataButton
	 */
	public function get_buttons() {
		return $this->buttons;
	}
	
	/**
	 * Returns an array of button widgets, that are explicitly bound to a double click on a data element
	 * @param string $mouse_action
	 * @return DataButton[]
	 */
	public function get_buttons_bound_to_mouse_action($mouse_action){
		$result = array();
		foreach ($this->get_buttons() as $btn){
			if ($btn->get_bind_to_mouse_action() == $mouse_action){
				$result[] = $btn;
			}
		}
		return $result;
	}
	
	/**
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::set_buttons()
	 */
	public function set_buttons(array $buttons_array) {
		if (!is_array($buttons_array)) return false;
		foreach ($buttons_array as $b){
			$button = $this->get_page()->create_widget('DataButton', $this, UxonObject::from_anything($b));
			$this->add_button($button);
		}
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::add_button()
	 */
	public function add_button(Button $button_widget){
		$button_widget->set_parent($this);
		$button_widget->set_meta_object_id($this->get_meta_object()->get_id());
		$this->buttons[] = $button_widget;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::has_buttons()
	 */
	public function has_buttons() {
		if (count($this->buttons)) return true;
		else return false;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::get_lazy_loading()
	 */
	public function get_lazy_loading() {
		return $this->get_data()->get_lazy_loading();
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::set_lazy_loading()
	 */
	public function set_lazy_loading($value) {
		return $this->get_data()->set_lazy_loading($value);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::get_lazy_loading_action()
	 */
	public function get_lazy_loading_action() {
		return $this->get_data()->get_lazy_loading_action();
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::set_lazy_loading_action()
	 */
	public function set_lazy_loading_action($value) {
		return $this->get_data()->set_lazy_loading_action($value);
	}
}
?>