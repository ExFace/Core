<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Chart;
use exface\Core\Widgets\Parts\Charts\LineChartSeries;
use exface\Core\Widgets\Parts\Charts\ChartSeries;
use exface\Core\Widgets\Parts\Charts\PieChartSeries;
use exface\Core\Widgets\Parts\Charts\DonutChartSeries;

/**
 * 
 * @method Chart getWidget()
 * @author rml
 *
 */
trait BillboardJsTrait
{
    protected function buildJsLiveReference()
    {
        $output = '';
        if ($link = $this->getWidget()->getDataWidgetLink()) {
            $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
            $output .= $this->buildJsFunctionPrefix() . 'plot(' . $linked_element->buildJsDataGetter() . ".rows);";
        }
        return $output;
    }
    
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
    
    protected function buildHtmlHeadDefaultIncludes()
    {
        $facade = $this->getFacade();
        $includes = [];
        //$includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.BILLBOARDJS.D3_JS') . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.BILLBOARDJS.BILLBOARDJS_JS') . '"></script>';
        $includes[]= '<link rel="stylesheet" href="' . $facade->buildUrlToSource('LIBS.BILLBOARDJS.BILLBOARDJS_CSS') . '">';
                
        return $includes;
    }
    
    protected function buildJsFunctions()
    {
        return <<<JS
  
    // Create the load function to fetch the data via AJAX or from another widget
    function {$this->buildJsFunctionPrefix()}load(){
        {$this->buildJsDataLoader()}
    }
    
JS;
    }
    
    public function buildJsRefresh()
    {
        return $this->buildJsFunctionPrefix() . 'load();';
    }
    
    abstract protected function buildJsDataLoader();
    
    public function buildJsBillboardInit() : string
    {
        return <<<JS
    var chart_{$this->getId()};
    setTimeout(function(){
        chart_{$this->getId()} = bb.generate({$this->buildJsChartConfig()});
        // Call the data loader to populate the Chart initially
        {$this->buildJsRefresh()}
    }, 0);

JS;
    }
        
    protected function buildJsChartConfig() : string
    {
        $series = '';
        foreach ($this->getWidget()->getSeries() as $series) {
            $series .= $this->buildJsChartSeriesConfig($series);
        }
        return <<<JS
        
{
    bindto: "#{$this->getId()}",
    transition: {
        duration: 500
    },
    axis: {
            x: {$this->buildJsAxis($this->getWidget()->getAxesX()[0])}
            y:  {$this->buildJsAxis($this->getWidget()->getAxesY()[0])}
    },
    data: {
        types: [
            
        ]
    }
    {$this->buildJsChartPropertyLegend()}
    {$this->buildJsChartPropertyPie()}
    {$this->buildJsChartPropertyDonut()}
    {$this->buildJsChartPropertyLine()}
    {$this->buildJsChartPropertyBar()}
    
}

JS;
    }
    
    protected function getSeriesKey(ChartSeries $series) : string
    {
        return $series->getValueDataColumn()->getDataColumnName();
    }
    
    protected function buildJsPropertyLine() : string
    {
        $opts = [];
        foreach ($this->getWidget()->getSeries() as $series) {
            if (! $series instanceof LineChartSeries) {
                continue;
            }
            
            $opts['point'][$this->getSeriesKey($series)] = true;
        }
        
        if (empty($opts) === false) {
            return 'line: ' . json_encode($opts) . ',';
        }
        return '';
    }
        
    protected function buildJsChartSeriesConfig(ChartSeries $series) : string
    {
        switch (true) {
            case $series instanceof LineChartSeries:
                return $this->buildJsLineChart($series);
            case $series instanceof PieChartSeries:
                return $this->buildJsPieChart($series);
        }
    }
    
    protected function buildJsLineChart(LineChartSeries $series) : string
    {
        return <<<JS


JS;
    }
        
    protected function buildJsPieChart(PieChartSeries $series) : string
    {
        return <<<JS
        
    data: { 
        columns: [],
        type: "pie"
    },        
    pie: {
		padding: 3,
		innerRadius: 15,
		label: {
			format: function(value, ratio, id) {
				return (value);
			},
		}
	},
    legend: {
        postion: "right"
    },
        
JS;
    }
        
    protected function buildJsAxis($axes) : string
    {
        return <<<JS
        
        {
            show: false
        },

JS;
    }
        
    protected function buildJsRedraw(string $dataJs) : string
    {
        if ($this->isPieOrDonutChartSeries() === true) {
            return <<<JS

var completeData = data.rows;
var arrayLength = completeData.length;
var chartData = [];
for (var i = 0; i < arrayLength; i++){
	var item = [completeData[i]["{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()}"], completeData[i]["{$this->getWidget()->getSeries()[0]->getValueDataColumn()->getDataColumnName()}"]];
	chartData.push(item);
}
chart_{$this->getId()}.load({				
	columns: chartData,
});
chart_{$this->getId()}.flush(true);
JS;
        } else {
            return <<<JS
            
var completeData = data.rows;
chart_{$this->getId()}.load({
	json: completeData,	
});

JS;
        }
    }

    protected function isPieOrDonutChartSeries()
    {
        if ($this->getWidget()->getSeries()[0] instanceof PieChartSeries || $this->getWidget()->getSeries()[0] instanceof DonutChartSeries) {
            return true;
        } else {
            return false;
        }
    }
    
}