<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Exceptions\Templates\TemplateUnsupportedWidgetPropertyWarning;
use exface\Core\Widgets\ChartSeries;
use exface\Core\Widgets\Chart;
use exface\Core\Widgets\ChartAxis;

/**
 * This trait contains common methods to use the flot charing library in jQuery templates.
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
            $linked_element = $this->getTemplate()->getElementByWidgetId($link->getWidgetId(), $this->getWidget()->getPage());
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
            /* @var $linked_element \exface\Templates\jEasyUI\Widgets\euiData */
            $linked_element = $this->getTemplate()->getElementByWidgetId($link->getWidgetId(), $this->getWidget()->getPage());
            if ($linked_element) {
                $linked_element->addOnLoadSuccess($this->buildJsLiveReference());
            }
        }
        return $this;
    }
    
    protected function buildJsPlotFunction()
    {
        $widget = $this->getWidget();
        $output = '';
        
        
        $series_data = '';
        
        if ($this->isPieChart()) {
            $this->getWidget()->setHideAxes(true);
        }
        
        // Create the function to process fetched data
        $output .= '
			function ' . $this->buildJsFunctionPrefix() . 'plot(data){
				';
        
        $js_rows = 'data' . $this->buildJsDataRowsSelector();
        
        // Transform the input data to a flot dataset
        foreach ($widget->getSeries() as $series) {
            $series_id = $this->sanitizeSeriesId($series->getId());
            $series_column = $series->getDataColumn();
            $x_column = $series->getAxisX()->getDataColumn();
            $y_column = $series->getAxisY()->getDataColumn();
            $output .= '
					var ' . $series_id . ' = [];';
            
            if ($series->getChartType() == ChartSeries::CHART_TYPE_PIE) {
                $series_data = $series_id . '[i] = { label: ' . $js_rows . '[i]["' . $x_column->getDataColumnName() . '"], data: ' . $js_rows . '[i]["' . $series_column->getDataColumnName() . '"] }';
            } else {
                // Prepare the code to transform the ajax data to flot data. It will later run in a for loop.
                switch ($series->getChartType()) {
                    case ChartSeries::CHART_TYPE_BARS:
                        $data_key = $series_column->getDataColumnName();
                        $data_value = $y_column->getDataColumnName();
                        break;
                    default:
                        $data_key = $x_column->getDataColumnName();
                        $data_value = $series_column->getDataColumnName();
                }
                $series_data .= '
							' . $series_id . '[i] = [ (' . $js_rows . '[i]["' . $data_key . '"]' . ($series->getAxisX()->getAxisType() == 'time' ? '*1000' : '') . '), ' . $js_rows . '[i]["' . $data_value . '"] ];';
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
        
        // End plot() function
        $output .= '}';
        
        // Create the load function to fetch the data via AJAX or from another widget
        $output .= $this->buildJsAjaxLoaderFunction();
        $output .= $this->buildJsTooltipInit();
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
        if ($this->getWidget()->getSeries()[0]->getChartType() == ChartSeries::CHART_TYPE_PIE) {
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
                throw new TemplateUnsupportedWidgetPropertyWarning('The template "' . $this->getTemplate()->getAlias() . '" does not support pie charts with multiple series!');
            }
            
            $output = $this->sanitizeSeriesId($this->getWidget()->getSeries()[0]->getId());
        } else {
            foreach ($this->getWidget()->getSeries() as $series) {
                if ($series->getChartType() == ChartSeries::CHART_TYPE_PIE) {
                    throw new TemplateUnsupportedWidgetPropertyWarning('The template "' . $this->getTemplate()->getAlias() . '" does not support pie charts with multiple series!');
                }
                $series_options = $this->buildJsSeriesOptions($series);
                $output .= ',
								{
									data: ' . $this->sanitizeSeriesId($series->getId()) . ($series->getChartType() == ChartSeries::CHART_TYPE_BARS ? '.reverse()' : '') . '
									, label: "' . $series->getCaption() . '"
									, yaxis:' . $series->getAxisY()->getNumber() . '
									, xaxis:' . $series->getAxisX()->getNumber() . '
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
        $color = $series->getDataColumn()->getColor();
        switch ($series->getChartType()) {
            case ChartSeries::CHART_TYPE_LINE:
            case ChartSeries::CHART_TYPE_AREA:
                $options = 'lines:
								{
									show: true
									' . ($series->getChartType() == ChartSeries::CHART_TYPE_AREA ? ', fill: true' : '') . '
                                }
                            ' . ($color ? ', color: "' . $color . '"' : '') . '';
                break;
            case ChartSeries::CHART_TYPE_BARS:
            case ChartSeries::CHART_TYPE_COLUMNS:
                $options = 'bars:
								{
									show: true
                                    , lineWidth: 0
									, align: "center"
                                    ';
                if (! $series->getChart()->getStackSeries() && count($series->getChart()->getSeriesByChartType($series->getChartType())) > 1) {
                    $options .= '
                                    , barWidth: 0.2
                                    , order: ' . $series->getSeriesNumber();
                } else {
                    $options .= '
                                    , barWidth: 0.8';
                }
                
                if ($series->getAxisX()->getAxisType() == ChartAxis::AXIS_TYPE_TIME || $series->getAxisY()->getAxisType() == ChartAxis::AXIS_TYPE_TIME) {
                    $options .= '
									, barWidth: 24*60*60*1000*0.8';
                }
                
                if ($series->getChartType() == ChartSeries::CHART_TYPE_BARS) {
                    $options .= '
									, horizontal: true';
                }
                
                $options .= '
								}
                            ' . ($color ? ', color: "' . $color . '"' : '') . '';
                break;
            case ChartSeries::CHART_TYPE_PIE:
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
    
    public function generateHeaders()
    {
        $includes = [];
        // flot
        $includes[] = '<script type="text/javascript" src="exface/vendor/npm-asset/flot-charts/jquery.flot.js"></script>';
        $includes[] = '<script type="text/javascript" src="exface/vendor/npm-asset/flot-charts/jquery.flot.resize.js"></script>';
        $includes[] = '<script type="text/javascript" src="exface/vendor/npm-asset/flot-charts/jquery.flot.categories.js"></script>';
        $includes[] = '<script type="text/javascript" src="exface/vendor/npm-asset/flot-charts/jquery.flot.time.js"></script>';
        $includes[] = '<script type="text/javascript" src="exface/vendor/npm-asset/flot-charts/jquery.flot.crosshair.js"></script>';
        
        if ($this->getWidget()->getStackSeries()) {
            $includes[] = '<script type="text/javascript" src="exface/vendor/npm-asset/flot-charts/jquery.flot.stack.js"></script>';
        }
        
        if ($this->isPieChart()) {
            $includes[] = '<script type="text/javascript" src="exface/vendor/npm-asset/flot-charts/jquery.flot.pie.js"></script>';
        }
        
        return $includes;
    }
    
    protected function buildJsSeriesConfig()
    {
        $output = '';
        $config_array = array();
        foreach ($this->getWidget()->getSeries() as $series) {
            switch ($series->getChartType()) {
                case ChartSeries::CHART_TYPE_PIE:
                    $config_array[$series->getChartType()]['show'] = 'show: true';
                    $config_array[$series->getChartType()]['radius'] = 'radius: 1';
                    $config_array[$series->getChartType()]['label'] = 'label: {
							show: true,
							radius: 0.8,
							formatter: function (label, series) {
								return "<div style=\'font-size:8pt; text-align:center; padding:2px; color:white;\'>" + label + "<br/>" + Math.round(series.percent) + "%</div>";
							},
							background: {opacity: 0.8}}';
                    break;
                case ChartSeries::CHART_TYPE_COLUMNS:
                case ChartSeries::CHART_TYPE_BARS:
                    
                    break;
                default:
                    break;
            }
        }
        
        if ($this->getWidget()->getStackSeries()) {
            $config_array['stack'] = 'true';
        }
        
        foreach ($config_array as $chart_type => $options) {
            $output .= $chart_type . ': ' . (is_array($options) ? '{' . implode(', ', $options) . '}' : $options) . ', ';
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
