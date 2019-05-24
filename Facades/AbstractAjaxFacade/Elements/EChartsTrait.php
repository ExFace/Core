<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;
use exface\Core\Interfaces\Widgets\iDisplayValue;
use exface\Core\Widgets\Chart;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Parts\Charts\BarChartSeries;
use exface\Core\Widgets\Parts\Charts\ChartAxis;
use exface\Core\Widgets\Parts\Charts\ChartSeries;
use exface\Core\Widgets\Parts\Charts\ColumnChartSeries;
use exface\Core\Widgets\Parts\Charts\DonutChartSeries;
use exface\Core\Widgets\Parts\Charts\LineChartSeries;
use exface\Core\Widgets\Parts\Charts\PieChartSeries;
use exface\Core\Widgets\Parts\Charts\RoseChartSeries;
use exface\Core\Widgets\Parts\Charts\SplineChartSeries;
use exface\Core\Widgets\Parts\Charts\Interfaces\StackableChartSeriesInterface;
use exface\Core\Widgets\Parts\Charts\AreaChartSeries;

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
            $output .= $this->buildJsRedrawFunctionName(). '(' . $linked_element->buildJsDataGetter() . ')';
            //$output .= $this->buildJsFunctionPrefix() . 'plot(' . $linked_element->buildJsDataGetter() . ".rows);";
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
    };

    function {$this->buildJsRedrawFunctionName()}(oData) {
        {$this->buildJsRedrawFunctionBody('oData')}
    };


    
JS;
    }

    protected function buildJsRedrawFunctionName() : string
    {
        return $this->buildJsFunctionPrefix() . 'redraw';
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
        {$this->buildJsOnClickFunction()}

        {$this->getOnChangeScript()}
        

JS;
    }
        
    protected function buildJsOnClickFunction() : string {
        return <<<JS

        {$this->buildJsEChartsVar()}.on('click', function(params){
            console.log(params);
            var dataRow = params.data;
            if (params.seriesType == 'pie') {
            
            } else {
                var options = {$this->buildJsEChartsVar()}.getOption();
                var newOptions = {series: []};        
                options.series.forEach((series) => {
                    newOptions.series.push({markLine: {data: {}}});                
                });            
                if (("_bar" in options.series[params.seriesIndex]) == true) {
                    newOptions.series[params.seriesIndex].markLine.data = [ 
                        {
            				yAxis: dataRow[options.series[params.seriesIndex].encode.y]
            			}
                    ];
                } else {  
                    newOptions.series[params.seriesIndex].markLine.data = [ 
                        {
            				xAxis: dataRow[options.series[params.seriesIndex].encode.x]
            			}
                    ];
                }
                {$this->buildJsEChartsVar()}.setOption(newOptions);
            }
            {$this->buildJsEChartsVar()}._selection = dataRow;
            {$this->getOnChangeScript()}
            console.log('data getter: ', {$this->buildJsValueGetter('Datum__Tag')});
    });

JS;
        //TODO
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
	
    tooltip : {$this->buildJsChartPropertyTooltip()}
   	legend: {$this->buildJsChartPropertyLegend()}
	series: [$seriesConfig],
    {$this->buildJsAxes()}
    {$this->buildJsZoom()}
    
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
            case $series instanceof BarChartSeries:
                return $this->buildJsBarChart($series);
            case $series instanceof ColumnChartSeries:
                return $this->buildJsColumnChart($series);
            case $series instanceof RoseChartSeries:
                return $this->buildJsRoseChart($series);
            case $series instanceof DonutChartSeries:
                return $this->buildJsPieChart($series);
            case $series instanceof PieChartSeries:
                return $this->buildJsPieChart($series);
            
        }
    }
    
    protected function buildJsLineChart(LineChartSeries $series) : string
    {
        if ($series instanceof AreaChartSeries || $series->isFilled() === true){
            if ($series->isFilled() === false) {
                $filledJs = '';
            } else {
                $filledJs = 'areaStyle: {},';
            }
        } else {
            $filledJs = '';
        }
        
        if ($series instanceof SplineChartSeries || $series->isSmooth() === true ){
            if ($series->isSmooth() === false) {
                $smoothJs = '';
            } else {
                $smoothJs = 'smooth: true,';
            }
        } else {
            $smoothJs = '';
        }
        
        return <<<JS

{
    name: '{$series->getCaption()}',
    type: 'line',
    encode: {
        x: '{$series->getXDataColumn()->getDataColumnName()}',
        y: '{$series->getYDataColumn()->getDataColumnName()}'
    },
    xAxisIndex: {$series->getXAxis()->getIndex()},
    yAxisIndex: {$series->getYAxis()->getIndex()},
    {$smoothJs}
    {$filledJs}
    {$this->buildJsStack($series)}
    {$this->buildJsMarkLineProperties()}
},

JS;
    }
    
    protected function buildJsColumnBarChartProperties (ColumnChartSeries $series) :string
    {
        return <<<JS

    name: '{$series->getCaption()}',
    type: 'bar',
    encode: {
        x: '{$series->getXDataColumn()->getDataColumnName()}',
        y: '{$series->getYDataColumn()->getDataColumnName()}'
    },
    xAxisIndex: {$series->getXAxis()->getIndex()},
    yAxisIndex: {$series->getYAxis()->getIndex()},
    {$this->buildJsStack($series)}
    {$this->buildJsMarkLineProperties()}

JS;
    }
        
    protected function buildJsColumnChart(ColumnChartSeries $series) : string
    {
       return <<<JS
        
{
{$this->buildJsColumnBarChartProperties($series)}  
},

JS;
    }
    
    protected function buildJsBarChart(BarChartSeries $series) : string
    {
        return <<<JS

{
{$this->buildJsColumnBarChartProperties($series)}
    _bar: true    
},

JS;
    }
       
    protected function buildJsStack(StackableChartSeriesInterface $series) : string
    {
        if ($series->isStacked() === true){
            if ($series->getStackGroupId() !== null && !empty($series->getStackGroupId())){
                $stack = "stack: '{$series->getStackGroupId()},'";
            } else {
                $stack = "stack: 'defaultstackgroup1',";
            }            
        } else {
            $stack = '';
        }        
        return $stack;
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
    label: {$label},
    roseType: '{$valueMode}'
    
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
    label: {$label},
    selectedMode: 'single',
    							
},
        
JS;
    }
        
    protected function buildJsAxes() : string
    {
        if ($this->isPieChartSeries() === true){
            return '';
        }
        $countAxisRight = 0;
        $countAxisLeft = 0;
        $widget = $this->getWidget();
        $xAxesJS = '';
        $yAxesJS = '';
        foreach ($widget->getAxesX() as $axis){
           $xAxesJS .= $this->buildJsAxisProperties($axis);
        }        
        foreach ($widget->getAxesY() as $axis){
            if ($axis->getPosition() === ChartAxis::POSITION_LEFT && $axis->isHidden() === false){
                $countAxisLeft++;
                $yAxesJS .= $this->buildJsAxisProperties($axis, $countAxisLeft);
            } elseif ($axis->getPosition() === ChartAxis::POSITION_RIGHT && $axis->isHidden() === false){
                $countAxisRight++;
                $yAxesJS .= $this->buildJsAxisProperties($axis, $countAxisRight);
            }            
        }   
        return <<<JS

xAxis: [$xAxesJS],
yAxis: [$yAxesJS],

JS;
    }
        
    protected function buildJsAxisProperties(ChartAxis $axis, int $nameGapMulti = 1) : string
    {
        if ($axis->hasGrid() === false){
            $grid = 'false';
        } else {
            $grid = 'true';
        }
        if ($axis->getMinValue() === null){
            $min = '';
        } else {
            $min = "min: '" . $axis->getMinValue() . "',";
        }
        if ($axis->getMaxValue() === null){
            $max = '';
        } else {
            $max = "max: '" . $axis->getMaxValue() . "',";
        }
        
        if ($axis->getDimension() == Chart::AXIS_X){
            $nameLocation = "nameLocation: 'center',";
        } else {
            $nameLocation = '';
        }
        
        $axisType = mb_strtolower($axis->getAxisType());
        $position = mb_strtolower($axis->getPosition());
        
        if ($axis->getDimension() == Chart::AXIS_Y){
            $nameGap = $this->baseAxisNameGap()* $nameGapMulti;
        } else {
            $nameGap = $this->baseAxisNameGap() * 1.5;
        }
        
        
        return <<<JS
        
    {
        id: '{$axis->getIndex()}',
        name: '{$axis->getCaption()}',
        $nameLocation
        type: '{$axisType}',
        splitLine: { show: $grid },
        position: '{$position}',
        show: false,
        nameGap: {$nameGap},
        axisLabel: {
            formatter: function(a) {
                return {$this->buildJsLabelFormatter($axis->getDataColumn(), 'a')}
            }
        },
        $min
        $max
    },

JS;
    }
        
    protected function buildJsZoom() : string
    {
        if ($this->isPieChartSeries() === true){
            return '';
        }
        return <<<JS

        dataZoom: [
        {
            type: 'slider',
            xAxisIndex: 0,
            filterMode: 'empty'
        },
        {
            type: 'slider',
            yAxisIndex: 0,
            filterMode: 'empty'
        },
        {
            type: 'inside',
            xAxisIndex: 0,
            filterMode: 'empty'
        },
        {
            type: 'inside',
            yAxisIndex: 0,
            filterMode: 'empty'
        }
        ],

JS;
    }
        
    protected function buildJsMarkLineProperties() : string
    {
        return <<<JS

    markLine: {
        data: {},
        silent: true,
        symbol: 'circle',
        animation: false,
        label: {
            show: true
        },
        lineStyle: {
            color: '#000',
            type: 'solid',
        }
    },

JS;
    }
        
    protected function buildJsLabelFormatter(DataColumn $col, string $js_var_value) : string
    {
        $cellWidget = $col->getCellWidget();
        
        if (($cellWidget instanceof iDisplayValue) && $cellWidget->getDisableFormatting()) {
            return '';
        }
        
        // Data type specific formatting
        $formatter_js = '';
        $cellTpl = $this->getFacade()->getElement($cellWidget);
        if (($cellTpl instanceof JsValueDecoratingInterface) && $cellTpl->hasDecorator()) {
            $formatter_js = $cellTpl->buildJsValueDecorator($js_var_value);
        }
        
        return $formatter_js ? $formatter_js : $js_var_value;
    }
        
    protected function baseAxisNameGap() : int
    {
        return 15;
    }
    
    protected function buildJsRedrawFunctionBody(string $dataJs) : string
    {
        if ($this->isPieChartSeries() === true) {
            $js = <<<JS

var arrayLength = rowData.length;
var chartData = [];
for (var i = 0; i < arrayLength; i++){
	var item = { value: rowData[i].{$this->getWidget()->getSeries()[0]->getValueDataColumn()->getDataColumnName()} , name: rowData[i].{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()} };
	chartData.push(item);
}

{$this->buildJsEChartsVar()}.setOption({
	series: [{
		data: chartData							
	}],
	legend: {
		data: rowData.{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()}
	}					
})
JS;
        } else {
            
            $axesOffsetCalc = '';
            $axesJsObjectInit = '';
            foreach ($this->getWidget()->getAxes() as $axis){
                if ($axis->isHidden() === true) {
                    continue;
                }
                
                $xAxisIndex = 0;
                if ($axis->getDimension() === Chart::AXIS_X) {
                    $offset = ++$xAxisIndex . ' * 20 * 2';
                } else {
                    $offset = 'len * 7';
                }
                $axesOffsetCalc .= <<<JS

        val = row['{$axis->getDataColumn()->getDataColumnName()}'];
        len = (typeof val === 'string' || val instanceof String ? val.length : val.toString().length);
        offset = {$offset};
        if (axes["{$axis->getDataColumn()->getDataColumnName()}"]['offset'] < offset) {
            axes["{$axis->getDataColumn()->getDataColumnName()}"]['offset'] = offset;
        }

JS;
                $postion = mb_strtolower($axis->getPosition());
                $axesJsObjectInit .= <<<JS

    axes["{$axis->getDataColumn()->getDataColumnName()}"] = {
        offset: 0,
        dimension: "{$axis->getDimension()}",
        position: "{$postion}"
    };

JS;
            }
            
            $js = <<<JS
            
    var keys = Object.keys(rowData[0]);
    var longestString = 0;
    
    
    
    var axes = {};
    {$axesJsObjectInit}
    
    // Danach
    var val, offset;
    var len = 0;
    $dataJs.rows.forEach(function(row){
        {$axesOffsetCalc}
    })
    
    var newOptions = {yAxis: [], xAxis: []};
    var axis;
    offsets = {
        'top': 0,
        'right': 0,
        'bottom': 0,
        'left': 0
    };
    for (var i in axes) {
        axis = axes[i];
        newOptions[axis.dimension + 'Axis'].push({
            offset: offsets[axis.position],
            show: true
        });
        offsets[axis.position] += axis.offset;
    }
    
    var gridmargin = offsets;
    gridmargin['top'] += {$this->buildJsGridMarginTop()};
    gridmargin['right'] += {$this->buildJsGridMarginRight()};
    gridmargin['bottom'] += {$this->buildJsGridMarginBottom()};
    gridmargin['left'] += {$this->buildJsGridMarginLeft()};
    
    newOptions.grid = gridmargin;
    newOptions.dataset = {source: rowData};

    {$this->buildJsEChartsVar()}.setOption(newOptions);
    
JS;
    }
        
    return <<<JS
    
    if (! $dataJs) {
        return;
    }
    
    var rowData = $dataJs.rows;
    if (! rowData || rowData.count === 0) {
        {$this->buildJsDataResetter()};
        return;
    }

$js

JS;
    }
    
    protected function buildJsGridMarginTop() : int
    {
        $baseMargin = 40;
        $countAxisLeft = 0;
        $countAxisRight = 0;        
        foreach ($this->getWidget()->getAxesY() as $axis){
            if ($axis->getPosition() === ChartAxis::POSITION_LEFT && $axis->isHidden() === false){
                $countAxisLeft++;
            } elseif ($axis->getPosition() === ChartAxis::POSITION_RIGHT && $axis->isHidden() === false){
                $countAxisRight++;
            }
        }
        if ($countAxisLeft >= $countAxisRight){
            return $baseMargin + $this->baseAxisNameGap() * $countAxisLeft;
        } else {
            return $baseMargin + $this->baseAxisNameGap() * $countAxisRight;
        }
    }
    
    protected function buildJsGridMarginRight() : int
    {             
        return 100;
    }
    
    protected function buildJsGridMarginBottom() : int
    {
        return 50;
    }
    
    protected function buildJsGridMarginLeft() : int
    {
        return 20;
    }

    protected function isPieChartSeries() : bool
    {
        if ($this->getWidget()->getSeries()[0] instanceof PieChartSeries || $this->getWidget()->getSeries()[0] instanceof DonutChartSeries) {
            return true;
        } else {
            return false;
        }
    }
            
    protected function buildJsChartPropertyTooltip() : string
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
    
    protected function buildJsChartPropertyLegend() : string
    {
        $padding = '';
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
        if ($this->getWidget()->getSeries()[0] instanceof PieChartSeries){            
            $padding = 'padding: [20,10,20,10],';
        }
        return <<<JS

{
	type: 'scroll',
    {$padding}    
    {$positionJs}

},

JS;
    }
        
    protected function buildJsChartPropertyGrid() : string
    {
        /*if ($this->getWidget()->getSeries()[0] instanceof PieChartSeries){
            return '{},';
        }
        
        $marginPerYAxis = $this->getWidget()->getYAxisOffset();
        $marginPerXAxis = $this->getWidget()->getXAxisOffset();
        $baseMarginTop = 60;
        $baseMarginBottom = 60;
        $baseMarginLeft = 60;
        $baseMarginRight = 60;
        $bottomAxisCount = 0;
        $topAxisCount = 0;
        $leftAxisCount = 0;
        $rightAxisCount = 0;
        
        foreach ($this->getWidget()->getAxesX() as $xAxis){
            if ($xAxis->getPosition() === ChartAxis::POSITION_BOTTOM){
                $bottomAxisCount++;
            } elseif ($xAxis->getPosition() === ChartAxis::POSITION_TOP){
                $topAxisCount++;
            }
        }            
        foreach ($this->getWidget()->getAxesY() as $yAxis){
            if ($yAxis->getPosition() === ChartAxis::POSITION_LEFT){
                $leftAxisCount++;
            } else if ($yAxis->getPosition() ===ChartAxis::POSITION_RIGHT){
                $rightAxisCount++;
            }
        }
        if ($bottomAxisCount > 0){
            $bottomAxisCount = $bottomAxisCount - 1;
        }
        if ($rightAxisCount > 0){
            $rightAxisCount = $rightAxisCount - 1;
        }
        if ( $leftAxisCount > 0){
            $leftAxisCount = $leftAxisCount - 1;
        }
        $bottomMargin = $baseMarginBottom + $bottomAxisCount * $marginPerXAxis;
        $topMargin = $baseMarginTop + $topAxisCount * $marginPerXAxis;
        $leftMargin = $baseMarginLeft + $leftAxisCount * $marginPerYAxis;
        $rightMargin = $baseMarginRight + $rightAxisCount * $marginPerYAxis;
        
        //return '{},';
        
        return <<<JS

{
	bottom: '$bottomMargin',
    top: '$topMargin',
	left: '$leftMargin',
	right: '$rightMargin',

	containLable: true,
},

JS;
      
    */}
        
    protected function buildJsEChartsVar() : string
    {
        return "chart_{$this->getId()}";
    }
    
    protected function buildJsEChartsResize() : string
    {
        return "{$this->buildJsEChartsVar()}.resize()";
    }
    
    public function buildJsValueGetter($column = null, $row = null)
    {
        return <<<JS

function(){
return {$this->buildJsEChartsVar()}._selection;
}()

JS;
    }
    
    /**
     * Returns a JS snippet, that empties the chart.
     *
     * @return string
     */
    protected function buildJsDataResetter() : string
    {
        // TODO
        return "";
    }
    
}