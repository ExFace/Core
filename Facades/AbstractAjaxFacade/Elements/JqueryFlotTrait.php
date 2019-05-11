<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\Core\Widgets\ChartSeries;
use exface\Core\Widgets\Chart;
use exface\Core\Widgets\Parts\Charts\ChartAxis;
use exface\Core\Widgets\Parts\Charts\PieChart;
use exface\Core\Widgets\Parts\Charts\BarChart;
use exface\Core\Widgets\Parts\Charts\LineChart;
use exface\Core\Widgets\Parts\Charts\ColumnChart;
use exface\Core\Widgets\Parts\Charts\AreaChart;

/**
 * This trait contains common methods to use the flot charing library in jQuery facades.
 * 
 * @method Chart getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryFlotTrait {

    protected function buildJsLiveReference()
    {
        $output = '';
        if ($link = $this->getWidget()->getDataWidgetLink()) {
            $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
            $output .= $this->buildJsFunctionPrefix() . 'plot(' . $linked_element->buildJsDataGetter() . ".rows);";
        }
        return $output;
    }
    
    /**
     * Returns the path to the rows array within the data object loaded from the server via AJAX.
     * 
     * E.g. if the server sends {rows: [...], totals: [...]} then this should return ".rows" (this is the default value).
     * 
     * @return string
     */
    protected function buildJsDataRowsSelector()
    {
        return '.rows';
    }

    /**
     * Makes sure, the Chart is always updated, once the linked data widget loads new data - of course, only if there is a data link defined!
     */
    protected function registerLiveReferenceAtLinkedElement()
    {
        if ($link = $this->getWidget()->getDataWidgetLink()) {
            $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
            if ($linked_element) {
                $linked_element->addOnLoadSuccess($this->buildJsLiveReference());
            }
        }
        return $this;
    }
    
    protected function buildJsFunctions()
    {
        return <<<JS
    
    // Plot given data on chart
    function {$this->buildJsFunctionPrefix()}plot(data){
	   {$this->buildJsPlotter('data')};
    }

    // Create the load function to fetch the data via AJAX or from another widget
    function {$this->buildJsFunctionPrefix()}load(){
        {$this->buildJsDataLoader()}
    }

    // Initialize the DOM element for the tooltip
    {$this->buildJsTooltipInit()}
            
    // Call the data loader to populate the Chart initially
    {$this->buildJsRefresh()}

JS;
    }
    
    /**
     * 
     * @param string $dataJs
     * @return string
     */
    protected function buildJsPlotter($dataJs = 'data') : string
    {
        $widget = $this->getWidget();
        $output = '';
        
        
        $series_data = '';
        
        if ($this->isPieChart()) {
            $this->getWidget()->setHideAxes(true);
        }
        
        $js_rows = $dataJs . $this->buildJsDataRowsSelector();
        
        // Transform the input data to a flot dataset
        foreach ($widget->getSeries() as $series) {
            $series_id = $this->sanitizeSeriesId($series->getId());
            $chartType = $series->getChartType();
            $series_column = $chartType->getValueDataColumn();
            $output .= '
					var ' . $series_id . ' = [];';
            
            if ($chartType instanceof PieChart) {
                $x_column = $chartType->getTextAxis()->getDataColumn();
                $y_column = $chartType->getValueAxis()->getDataColumn();
                $series_data = $series_id . '[i] = { label: ' . $js_rows . '[i]["' . $x_column->getDataColumnName() . '"], data: ' . $js_rows . '[i]["' . $series_column->getDataColumnName() . '"] }';
            } else {
                $x_column = $chartType->getAxisX()->getDataColumn();
                $y_column = $chartType->getAxisY()->getDataColumn();
                // Prepare the code to transform the ajax data to flot data. It will later run in a for loop.
                switch (true) {
                    case $chartType instanceof BarChart:
                        $data_key = $series_column->getDataColumnName();
                        $data_value = $y_column->getDataColumnName();
                        break;
                    default:
                        $data_key = $x_column->getDataColumnName();
                        $data_value = $series_column->getDataColumnName();
                }
                $series_data .= '
							' . $series_id . '[i] = [ (' . $js_rows . '[i]["' . $data_key . '"]' . ($chartType->getAxisX()->getAxisType() == 'time' ? '*1000' : '') . '), ' . $js_rows . '[i]["' . $data_value . '"] ];';
            }
        }
        
        // Prepare other flot options
        $series_config = $this->buildJsSeriesConfig();
        
        foreach ($widget->getAxesX() as $axis) {
            if (! $axis->isHidden()) {
                $axis_x_init .= ', ' . $this->buildJsAxisOptions($axis);
            }
        }
        foreach ($widget->getAxesY() as $axis) {
            if (! $axis->isHidden()) {
                $axis_y_init .= ', ' . $this->buildJsAxisOptions($axis);
            }
        }
        
        // Plot flot :)
        $output .= '
					for (var i=0; i < ' . $js_rows . '.length; i++){
						' . $series_data . '
					}
						    
					$.plot("#' . $this->getId() . '",
						' . $this->buildJsSeriesData() . ',
						{
							grid:  { ' . $this->buildJsGridOptions() . ' }
							, crosshair: {mode: "xy"}
							' . ($axis_y_init ? ', yaxes: [ ' . substr($axis_y_init, 2) . ' ]' : '') . '
							' . ($axis_x_init ? ', xaxes: [ ' . substr($axis_x_init, 2) . ' ]' : '') . '
							' . ($series_config ? ', series: { ' . $series_config . ' }' : '') . '
							, legend: { ' . $this->buildJsLegendOptions() . ' }
						}
					);
							    
					$(".axisLabels").css("color", "black");
					';
        
        // Call the on_change_script
        $output .= $this->getOnChangeScript();
        
        return $output;
    }
    
    protected function buildJsGridOptions()
    {
        return 'hoverable: true';
    }
    
    protected function buildJsLegendOptions()
    {
        $output = '';
        if ($this->isPieChart()) {
            $output .= 'show: false';
        } else {
            $output .= $this->buildJsLegendOptionsAlignment();
        }
        return $output;
    }
    
    protected function buildJsLegendOptionsAlignment()
    {
        $options = '';
        switch (strtoupper($this->getWidget()->getLegendAlignment())) {
            case 'LEFT': $options = 'position: "nw"'; break;
            case 'RIGHT':
            default: $options = 'position: "ne"';
            
        }
        
        return $options;
    }
    
    protected function isPieChart()
    {
        if ($this->getWidget()->getSeries()[0]->getChartType() instanceof PieChart) {
            return true;
        } else {
            return false;
        }
    }
    
    protected function buildJsSeriesData()
    {
        $output = '';
        if ($this->isPieChart()) {
            if (count($this->getWidget()->getSeries()) > 1) {
                throw new FacadeUnsupportedWidgetPropertyWarning('The facade "' . $this->getFacade()->getAlias() . '" does not support pie charts with multiple series!');
            }
            
            $output = $this->sanitizeSeriesId($this->getWidget()->getSeries()[0]->getId());
        } else {
            foreach ($this->getWidget()->getSeries() as $series) {
                if ($series->getChartType() instanceof PieChart) {
                    throw new FacadeUnsupportedWidgetPropertyWarning('The facade "' . $this->getFacade()->getAlias() . '" does not support pie charts with multiple series!');
                }
                $series_options = $this->buildJsSeriesOptions($series);
                $output .= ',
								{
									data: ' . $this->sanitizeSeriesId($series->getId()) . ($series->getChartType() instanceof BarChart ? '.reverse()' : '') . '
									, label: "' . $series->getCaption() . '"
									, yaxis:' . $series->getChartType()->getAxisY()->getIndex() . '
									, xaxis:' . $series->getChartType()->getAxisX()->getIndex() . '
									' . ($series_options ? ', ' . $series_options : '') . '
								}';
            }
            $output = '[' . substr($output, 2) . ']';
        }
        return $output;
    }
    
    public function buildJsRefresh()
    {
        return $this->buildJsFunctionPrefix() . 'load();';
    }
    
    /**
     * Returns an inline JS snippet (no semicolon!) to redraw the chart using the data from the given JS expression.
     * 
     * By default functionPrefix_plot(data)
     * 
     * @param string $dataJs
     * @return string
     */
    protected function buildJsRedraw(string $dataJs) : string
    {
        return $this->buildJsFunctionPrefix() . 'plot(' . $dataJs . ')';
    }
    
    protected function buildJsTooltipInit()
    {
        // Create a tooltip generator function
        // TODO didn't work because I don't know, how to get the axes infomration from an instantiated plot
        $output = '
		 $(\'<div class="tooltip-inner" id="' . $this->getId() . '_tooltip"></div>\').css({
		      position: "absolute",
		      display: "none",
		      opacity: 0.8
		    }).appendTo("body");
		    $("#' . $this->getId() . '").bind("plothover", function (event, pos, item) {
		      if (item) {
                try {
    		        var x = new Date(item.datapoint[0]),
    		            y = isNaN(item.datapoint[1]) ? item.datapoint[1] : item.datapoint[1].toFixed(2);
		        
    		        $("#' . $this->getId() . '_tooltip").html(item.series.xaxis.options.axisLabel + ": " + x.toLocaleDateString() + "<br/>" + item.series.label + ": " + y)
    		            .css({top: item.pageY + 5, left: item.pageX + 5})
    		            .fadeIn(200);
                } catch (e) {
                    // ignore errors
                }
		      } else {
		        $("#' . $this->getId() . '_tooltip").hide();
		      }
		            
		    });
				';
        return $output;
    }
    
    public function sanitizeSeriesId($string)
    {
        return str_replace(array(
            '.',
            '(',
            ')',
            '=',
            ',',
            ' '
        ), '_', $string);
    }
    
    protected function buildJsSeriesOptions(ChartSeries $series)
    {
        $options = '';
        $color = $series->getColor();
        $chartType = $series->getChartType();
        switch (true) {
            
            case $chartType instanceof ColumnChart:
                $options = 'bars:
								{
									show: true
                                    , lineWidth: 0
									, align: "center"
                                    ';
                if ($chartType->isStacked() === true) {
                    $options .= '
                                    , barWidth: 0.2
                                    , order: ' . $series->getChart()->getSeriesIndex($series);
                } else {
                    $options .= '
                                    , barWidth: 0.8';
                }
                
                if ($chartType->getAxisX()->getAxisType() == ChartAxis::AXIS_TYPE_TIME || $chartType->getAxisY()->getAxisType() == ChartAxis::AXIS_TYPE_TIME) {
                    $options .= '
									, barWidth: 24*60*60*1000*0.8';
                }
                
                if ($chartType instanceof BarChart) {
                    $options .= '
									, horizontal: true';
                }
                
                $options .= '
								}
                            ' . ($color ? ', color: "' . $color . '"' : '') . '';
                break;
            case $chartType instanceof LineChart:
                $options = 'lines:
								{
									show: true
									' . ($chartType instanceof AreaChart ? ', fill: true' : '') . '
                                }
                            ' . ($color ? ', color: "' . $color . '"' : '') . '';
                break;
            case $chartType instanceof PieChart:
                $options = 'pie: {show: true}';
                break;
        }
        return $options;
    }
    
    protected function buildJsAxisOptions(ChartAxis $axis)
    {
        /* @var $widget \exface\Core\Widgets\Chart */
        $widget = $this->getWidget();
        $output = '{
								axisLabel: "' . $axis->getCaption() . '"
								, position: "' . strtolower($axis->getPosition()) . '"' . ($axis->getPosition() == ChartAxis::POSITION_RIGHT || $axis->getPosition() == ChartAxis::POSITION_TOP ? ', alignTicksWithAxis: 1' : '') . (is_numeric($axis->getMinValue()) ? ', min: ' . $axis->getMinValue() : '') . (is_numeric($axis->getMaxValue()) ? ', max: ' . $axis->getMaxValue() : '');
        
        switch ($axis->getAxisType()) {
            case ChartAxis::AXIS_TYPE_TEXT:
                $output .= '
								, mode: "categories"';
                break;
            case ChartAxis::AXIS_TYPE_TIME:
                $output .= '
								, mode: "time"';
                break;
            default:
        }
        
        $output .= '
					}';
        return $output;
    }
    
    protected function buildHtmlHeadDefaultIncludes()
    {
        $facade = $this->getFacade();
        $includes = [];
        // flot
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.FLOT.CORE_FOLDER') . 'jquery.flot.js"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.FLOT.CORE_FOLDER') . 'jquery.flot.resize.js"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.FLOT.CORE_FOLDER') . 'jquery.flot.categories.js"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.FLOT.CORE_FOLDER') . 'jquery.flot.time.js"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.FLOT.CORE_FOLDER') . 'jquery.flot.crosshair.js"></script>';
        
        foreach ($this->getWidget()->getSeries() as $series) {
            $type = $series->getChartType();
            if ($type instanceof ColumnChart && $type->isStacked()) {
                $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.FLOT.CORE_FOLDER') . 'jquery.flot.stack.js"></script>';
            }
        }
        
        if ($this->isPieChart()) {
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.FLOT.CORE_FOLDER') . 'jquery.flot.pie.js"></script>';
        }
        
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.FLOT.PLUGINS.AXISLABELS') . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.FLOT.PLUGINS.ORDERBARS') . '"></script>';
        
        return $includes;
    }
    
    protected function buildJsSeriesConfig()
    {
        $output = '';
        $config_array = array();
        foreach ($this->getWidget()->getSeries() as $series) {
            $chartType = $series->getChartType();
            switch (true) {
                case $chartType instanceof PieChart:
                    $config_array['pie']['show'] = 'show: true';
                    $config_array['pie']['radius'] = 'radius: 1';
                    $config_array['pie']['label'] = 'label: {
							show: true,
							radius: 0.8,
							formatter: function (label, series) {
								return "<div style=\'font-size:8pt; text-align:center; padding:2px; color:white;\'>" + label + "<br/>" + Math.round(series.percent) + "%</div>";
							},
							background: {opacity: 0.8}}';
                    break;
                case $chartType instanceof ColumnChart:
                    
                    break;
                default:
                    break;
            }
        }
        
        if ($chartType instanceof ColumnChart && $chartType->isStacked() === true) {
            $config_array['stack'] = 'true';
        }
        
        foreach ($config_array as $flot_chart_type => $options) {
            $output .= $flot_chart_type . ': ' . (is_array($options) ? '{' . implode(', ', $options) . '}' : $options) . ', ';
        }
        
        $output = $output ? substr($output, 0, - 2) : $output;
        return $output;
    }
    
    public function addOnChangeScript($string)
    {
        $this->on_change_script .= $string . ';';
        return $this;
    }
    
    public function getOnChangeScript()
    {
        return $this->on_change_script;
    }
}
?>
