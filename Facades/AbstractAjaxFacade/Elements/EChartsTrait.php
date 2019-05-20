<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\Core\Widgets\Chart;
use exface\Core\Widgets\Parts\Charts\LineChartSeries;
use exface\Core\Widgets\Parts\Charts\ChartSeries;
use exface\Core\Widgets\Parts\Charts\PieChartSeries;
use exface\Core\Widgets\Parts\Charts\DonutChartSeries;
use exface\Core\Widgets\Parts\Charts\RoseChartSeries;

/**
 * 
 * @method Chart getWidget()
 * @author rml
 *
 */
trait EChartsTrait
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
        
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.ECHARTS.ECHARTS_JS') . '"></script>';
        
                
        return $includes;
    }
    
    protected function buildJsFunctions()
    {
        return <<<JS
  
    // Create the load function to fetch the data via AJAX or from another widget
    function {$this->buildJsDataLoadFunctionName()}() {
        {$this->buildJsDataLoadFunctionBody()}
    }
    
JS;
    }
    
    public function buildJsRefresh()
    {
        return $this->buildJsDataLoadFunctionName() . '();';
    }
    
    protected function buildJsDataLoadFunctionName() : string
    {
        return $this->buildJsFunctionPrefix() . 'load';
    }
    
    abstract protected function buildJsDataLoadFunctionBody();
    
    public function buildJsEChartsInit() : string
    {
        return <<<JS
    var {$this->buildJsEChartsVar()} = echarts.init(document.getElementById('{$this->getId()}'));
    setTimeout(function(){
        {$this->buildJsEChartsVar()}.setOption({$this->buildJsChartConfig()});
        // Call the data loader to populate the Chart initially
        {$this->buildJsRefresh()}
    }, 1000);

JS;
    }
        
    protected function buildJsChartConfig() : string
    {
        $series = $this->getWidget()->getSeries();
        $seriesConfig = '';
        foreach ($series as $s) {
            if ($s instanceof PieChartSeries && count($series) > 1) {
                throw new FacadeUnsupportedWidgetPropertyWarning('The facade "' . $this->getFacade()->getAlias() . '" does not support pie charts with multiple series!');
            }
            $seriesConfig .= $this->buildJsChartSeriesConfig($s);
        }
        return <<<JS

{
	grid: {$this->buildJsChartPropertyGrid()}
    tooltip : {$this->buildJsChartPropertyTooltip()}
   	legend: {$this->buildJsChartPropertyLegend()}
	series: $seriesConfig
    {$this->buildJsChartPropertyPie()}
}

JS;
    }
    
    protected function getSeriesKey(ChartSeries $series) : string
    {
        return $series->getValueDataColumn()->getDataColumnName();
    }
    
    /*protected function buildJsPropertyLine() : string
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
    }*/
        
    protected function buildJsChartSeriesConfig(ChartSeries $series) : string
    {
        switch (true) {
            case $series instanceof LineChartSeries:
                return $this->buildJsLineChart($series);
            case $series instanceof RoseChartSeries:
                return $this->buildJsRoseChart($series);
            /*case $series instanceof DonutChartSeries:
                return $this->buildJsPieChart($series);*/
            case $series instanceof PieChartSeries:
                return $this->buildJsPieChart($series);
            
        }
    }
    
    protected function buildJsLineChart(LineChartSeries $series) : string
    {
        return <<<JS

{
    name: {$this->getWidget()->getSeries()->get
}

JS;
    }
        
    protected function buildJsRoseChart(RoseChartSeries $series) : string
    {
        $label = '{}';
        $position = $this->getWidget()->getLegendPosition();
        if ($position !== null){
            $label = '{show: false}';
        }
        if($position == 'top' || $position == 'bottom' || $position == null){
            $centerX = '50%';
        } elseif ($position == 'left'){
            $centerX = '70%';
        } elseif ($position == 'right'){
            $centerX = '30%';
        }
        
        $valueMode = $series->getValueMode();
        if ($valueMode == null){
            $valueMode = '';
        } elseif ($valueMode == 'angle'){
            $valueMode = 'radius';
        } elseif( $valueMode == 'radius'){
            $valueMode = 'area';
        }
        
        $radius = $series->getInnerRadius();
        return <<<JS
        
{
    type: 'pie',
    radius: ['$radius', '80%'],
    center: ['$centerX', '50%'],
    data: [],
    label: $label,
    roseType: '$valueMode'
    
},

JS;
    }  

    protected function buildJsPieChart(PieChartSeries $series) : string
    {
        $label = '{}';
        $position = $this->getWidget()->getLegendPosition();
        if ($position !== null){
            $label = '{show: false}';
        }
        if($position == 'top' || $position == 'bottom' || $position == null){
            $centerX = '50%';
        } elseif ($position == 'left'){
            $centerX = '70%';
        } elseif ($position == 'right'){
            $centerX = '30%';
        }
        
        $radius = $series->getInnerRadius();
        return <<<JS
        
{
    type: 'pie',
    radius: ['$radius','80%'],
    center: ['$centerX', '50%'],
    data: [],
    label: $label,
    roseType: '$roseType'
    							
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
        if ($this->isPieChartSeries() === true) {
            return <<<JS

var completeData = $dataJs.rows;
var arrayLength = completeData.length;
var chartData = [];
for (var i = 0; i < arrayLength; i++){
	var item = { value: completeData[i].{$this->getWidget()->getSeries()[0]->getValueDataColumn()->getDataColumnName()} , name: completeData[i].{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()} };
	chartData.push(item);
}

chart_{$this->getId()}.setOption({
	series: [{
		data: chartData							
	}],
	legend: {
		data: completeData.{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()}
	}					
})
JS;
        } else {
            return <<<JS
            
var chartData = $dataJs.rows;
chart_{$this->getId()}.setOption({
	dataset: {
	source: chartData
	},
});

JS;
        }
    }

    protected function isPieChartSeries()
    {
        if ($this->getWidget()->getSeries()[0] instanceof PieChartSeries || $this->getWidget()->getSeries()[0] instanceof DonutChartSeries) {
            return true;
        } else {
            return false;
        }
    }
    
    protected function buildJsChartPropertyPie()
    {
        if ($this->isPieChartSeries() === true) {
        //TODO check if Nightingale rose, get if area or radius
            return <<<JS



JS;
        } else {
            return '';
        }
    }
        
    protected function buildJsChartPropertyTooltip()
    {
        if ($this->isPieChartSeries() === true) {
            return <<<JS

{
	trigger: 'item',
	formatter: "{b} : {c} ({d}%)"
},

JS;
        } else {
            return <<<JS

{
	trigger: 'axis',
	axisPointer: {
		type: 'cross'
	}
},

JS;
        }        
    }
    
    protected function buildJsChartPropertyLegend()
    {
        $position = $this->getWidget()->getLegendPosition();
        if ($position === null && $this->getWidget()->getSeries()[0] instanceof PieChartSeries) {
            $positionJs = "show: false";
        } elseif ($position == 'top' ){
            $positionJs = "top: 'top',";            
        } elseif ($position == 'bottom'){
            $positionJs = "top: 'bottom',";  
        } elseif ($position == 'left'){
            $positionJs = "left: 'left', orient: 'vertical',";
        } elseif ($position == 'right'){
            $positionJs = "left: 'right', orient: 'vertical',";
        }
        return <<<JS

{
	type: 'scroll',
    $positionJs

},

JS;
    }
        
    protected function buildJsChartPropertyGrid()
    {
        return <<<JS

{
	containLable: true,
},

JS;
    }
        
    protected function buildJsEChartsVar() : string
    {
        return "chart_{$this->getId()}";
    }
    
    protected function buildJsEChartsResize() : string
    {
        return "{$this->buildJsEChartsVar()}.resize()";
    }
    
}