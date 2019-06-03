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
use exface\Core\Exceptions\Facades\FacadeOutputError;
use exface\Core\Widgets\Parts\Charts\Traits\XYChartSeriesTrait;

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
            $output .= $this->buildJsRedraw($linked_element->buildJsDataGetter().'.rows');
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
    
    protected function buildHtmlChart($style = 'height:100%; min-height: 100px; overflow: hidden;') : string
    {
        return '<div id="' . $this->getId() . '" style="' . $style . '"></div>';
    }
    
    protected function buildHtmlHeadDefaultIncludes()
    {
        $facade = $this->getFacade();
        $includes = [];
        
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.ECHARTS.ECHARTS_JS') . '"></script>';
        
        
        foreach ($this->getWidget()->getData()->getColumns() as $col) {
            $formatter = $this->getFacade()->getDataTypeFormatter($col->getDataType());
            $includes = array_merge($includes, $formatter->buildHtmlBodyIncludes());
        }
                
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

    function {$this->buildJsSelectFunctionName()}(selection) {
        {$this->buildJsSelectFunctionBody('selection')}
    };
    
JS;
    }
    
    protected function buildJsRedraw(string $dataJs) : string
    {
        return $this->buildJsRedrawFunctionName(). '(' . $dataJs . ')';
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
    
    public function buildJsEChartsInit($theme = null) : string
    {
        return <<<JS

    var {$this->buildJsEChartsVar()} = echarts.init(document.getElementById('{$this->getId()}'), '{$theme}');

JS;
    }
        
    protected function buildJsSelect(string $oRowJs = '') : string
    {
        return $this->buildJsSelectFunctionName() . '(' . $oRowJs . ')';
    }
        
    protected function buildJsSelectFunctionName() : string
    {
        return $this->buildJsFunctionPrefix() . 'select';
    }
    
    protected function buildJsSelectFunctionBody(string $selection) : string
    {
        return <<<JS
            var echart = {$this->buildJsEChartsVar()};
            var oSelectedRow = {$selection};
            console.log('selected', oSelectedRow);
            if (typeof echart._oldselection === 'undefined') {
                echart._oldSelection = oSelectedRow;
            } else {
                if (({$this->buildJsRowCompare('echart._oldSelection', 'oSelectedRow')}) === false) {
                    echart._oldSelection = oSelectedRow;
                } else {
                    return;
                }
            }

            {$this->getOnChangeScript()}
    
JS;
    }
                
    protected function buildJsRowCompare(string $leftRowJs, string $rightRowJs) : string
    {
        return "(JSON.stringify({$leftRowJs}) == JSON.stringify({$rightRowJs}))";
    }
        
    protected function buildJsOnClickHandlers() : string {
        return <<<JS

        {$this->buildJsEChartsVar()}.on('click', function(params){
            var dataRow = params.data;

            if (params.seriesType == 'pie') {
                if ((typeof {$this->buildJsEChartsVar()}._oldSelection != 'undefined') && ({$this->buildJsRowCompare($this->buildJsEChartsVar() . '._oldSelection', 'dataRow')}) == true) {
                    {$this->buildJsSelect()}                    
                } else {
                    {$this->buildJsSelect('dataRow')}
                }            
            } else {
                var options = {$this->buildJsEChartsVar()}.getOption();
                var newOptions = {series: []};
                var sameValue = false;
                options.series.forEach((series) => {
                    newOptions.series.push({markLine: {data: {}}});                
                });
                if ((typeof {$this->buildJsEChartsVar()}._oldSelection != 'undefined') && ({$this->buildJsRowCompare($this->buildJsEChartsVar() . '._oldSelection', 'dataRow')}) == true) {
                    {$this->buildJsSelect()}                    
                } else {
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
                    {$this->buildJsSelect('dataRow')}
                }
                {$this->buildJsEChartsVar()}.setOption(newOptions);
            }
            
            /*{$this->getOnChangeScript()}*/
            
    });

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
            
            if ($s instanceof BarChartSeries && $s->getIndex() != 0) {
                if ($series[$s->getIndex() - 1] instanceof BarChartSeries === false) {
                    throw new FacadeUnsupportedWidgetPropertyWarning('The facade "' . $this->getFacade()->getAlias() . '" does not support bar charts mixed with other chart types!');
                }
            }
        }
            
        return <<<JS

{
	
    tooltip : {$this->buildJsChartPropertyTooltip()}
   	legend: {$this->buildJsChartPropertyLegend()}
	series: [$seriesConfig],
    {$this->buildJsAxes()}
    
}

JS;
    }
    
    protected function getSeriesKey(ChartSeries $series) : string
    {
        return $series->getValueDataColumn()->getDataColumnName();
    }
        
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
        
        if ($series->getColor() !== null) {
            $color = "lineStyle: { color: '{$series->getColor()}' },";
        } else {
            $color = '';
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
    {$color}
    {$this->buildJsStack($series)}
    {$this->buildJsMarkLineProperties()}
},

JS;
    }
    
    protected function buildJsColumnBarChartProperties (ColumnChartSeries $series) :string
    {
        if ($series->getColor() !== null) {
            $color = "itemStyle: { color: '{$series->getColor()}' },";
        } else {
            $color = '';
        }
        
        return <<<JS

    name: '{$series->getCaption()}',
    type: 'bar',
    encode: {
        x: '{$series->getXDataColumn()->getDataColumnName()}',
        y: '{$series->getYDataColumn()->getDataColumnName()}'
    },
    xAxisIndex: {$series->getXAxis()->getIndex()},
    yAxisIndex: {$series->getYAxis()->getIndex()},
    {$color}
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
    radius: ['$radius','60%'],
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
        $zoom = '';
        foreach ($widget->getAxesX() as $axis){
           $xAxesJS .= $this->buildJsAxisProperties($axis);
           $zoom .= $this->buildJsAxisZoom($axis);
        }        
        foreach ($widget->getAxesY() as $axis){
            $zoom .= $this->buildJsAxisZoom($axis);
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
dataZoom: [$zoom],

JS;
    }
        
    protected function buildJsAxisProperties(ChartAxis $axis, int $nameGapMulti = 1) : string
    {
        if ($axis->getHideCaption() === false) {
            $name = $axis->getCaption();
        } else {
            $name = '';
        }
        
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
            if ($axis->isReverse() === true) {
                $inverse = "inverse: true,";
                $nameLocation = "nameLocation: 'start',";
            } else {
                $inverse = '';
            }
        } else {
            $nameGap = $this->baseAxisNameGap() * 1.5;
        }
        
        
        
        
        return <<<JS
        
    {
        id: '{$axis->getIndex()}',
        name: '{$name}',        
        {$nameLocation}
        {$inverse}
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
        {$min}
        {$max}
    },

JS;
    }
        
    protected function buildJsAxisZoom(ChartAxis $axis) : string
    {
        if ($axis->isZoomable() === true){
            if ($this->getWidget()->getLegendPosition() === 'bottom') {
                $bottom = 'bottom: 25';
            } else {
                $bottom = '';
            }
                $zoom = <<<JS

        {
            type: 'slider',
            {$axis->getDimension()}AxisIndex: {$axis->getIndex()},
            filterMode: 'filter',
            {$bottom}
        },
        {
            type: 'inside',
            {$axis->getDimension()}AxisIndex: {$axis->getIndex()},
            filterMode: 'filter'
        },

JS;
        } else {
            $zoom = '';
        }        
        return $zoom;
    }
        
    protected function buildJsMarkLineProperties() : string
    {
        return <<<JS

    markLine: {
        data: [],
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
        $formatter = $this->getFacade()->getDataTypeFormatter($col->getDataType());
        
        return $formatter->buildJsFormatter($js_var_value);
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
        if (val === undefined) {
            len = 0;
        } else {
            len = (typeof val === 'string' || val instanceof String ? val.length : val.toString().length);
        }
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
        position: "{$postion}",
        index: "{$axis->getIndex()}",
        name: "{$axis->getDataColumn()->getDataColumnName()}",
    };

JS;
            }
            
            $js = <<<JS
            
    //var keys = Object.keys(rowData[0]);
    var longestString = 0;

    var axes = {};
    {$axesJsObjectInit}
    
    // Danach
    var val, offset;
    var len = 0;
    rowData.forEach(function(row){
        {$axesOffsetCalc}
    })
    
    var newOptions = {yAxis: [], xAxis: []};
    var axis;
    var offsets = {
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
        
        if (axis.offset === 0) {
            {$this->buildJsShowMessageError("'{$this->getWorkbench()->getCoreApp()->getTranslator()->translate('ERROR.ECHARTS.AXIS_NO_DATA')} \"' + axis.name + '\"'")}
        }
        
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
    
    var rowData = $dataJs;
    if (! rowData || rowData.count === 0) {
        {$this->buildJsDataResetter()};
        {$this->buildJsMessageOverlayShow($this->getWidget()->getEmptyText())}
        return;
    }
{$this->buildJsMessageOverlayHide()}
{$this->buildJsEChartsVar()}.setOption({$this->buildJsChartConfig()});
$js

JS;
    }

    protected function buildJsMessageOverlayShow(string $message) : string
    {
        return <<<JS

$({$this->buildJsEChartsVar()}.getDom()).prepend($('<div class="exf-chart-message" style="position: absolute; padding: 10px; width: 100%; text-align: center;">{$message}</div>'));

JS;
    }
    
    protected function buildJsMessageOverlayHide() : string
    {
        return <<<JS
if ($(".exf-chart-message")[0]) {       
    $(".exf-chart-message").remove();
}

JS;
        
    }
    
    protected function buildJsGridMarginTop() : int
    {
        $baseMargin = 10;
        $countAxisLeft = 0;
        $countAxisRight = 0;        
        $widget = $this->getWidget();
        foreach ($this->getWidget()->getAxesY() as $axis){
            if ($axis->getPosition() === ChartAxis::POSITION_LEFT && $axis->isHidden() === false && $axis->getHideCaption() === false ){
                $countAxisLeft++;
            } elseif ($axis->getPosition() === ChartAxis::POSITION_RIGHT && $axis->isHidden() === false && $axis->getHideCaption() === false){
                $countAxisRight++;
            }
        }
        if ($countAxisLeft > 0 || $countAxisRight > 0) {
            $margin = 10;
        }
        if ($countAxisLeft >= $countAxisRight){
            $margin += $this->baseAxisNameGap() * $countAxisLeft;
        } else {
            $margin += $this->baseAxisNameGap() * $countAxisRight;
        }
        
        if ($this->legendHidden() === false && ($widget->getLegendPosition() === 'top' || $widget->getLegendPosition() === null)) {
            $margin += 20;
        }
        return $baseMargin + $margin;
    }
    
    protected function buildJsGridMarginRight() : int
    {             
        $count = 0;
        $rightAxis = false;
        foreach ($this->getWidget()->getAxesY() as $axis) {
            if ($axis->isZoomable() === true) {
                $count++;    
            }
            if ($axis->getPosition() === ChartAxis::POSITION_RIGHT) {
                $rightAxis = true;
            }
        }        
        if ($rightAxis === true) {
            $basemargin = 0;
        } else {
            $basemargin = 40;
        }
        $margin = $basemargin + 40*$count;
        return  $margin;       
    }
    
    protected function buildJsGridMarginBottom() : int
    {
        $count = 0;
        $widget = $this->getWidget();
        foreach ($widget->getAxesX() as $axis) {
            if ($axis->isZoomable() === true) {
                $count++;
            }
        }
        if ($this->legendHidden() === false && $widget->getLegendPosition() === 'bottom') {
            $margin += 20;
        }
        $margin += 5+40*$count;        
        return $margin;
    }
    
    protected function buildJsGridMarginLeft() : int
    {
        $leftAxis = false;
        foreach ($this->getWidget()->getAxesY() as $axis) {
            if ($axis->getPosition() === ChartAxis::POSITION_LEFT) {
                $leftAxis = true;
            }
        }        
        if ($leftAxis === true) {
            $basemargin = 5;
        } else {
            $basemargin = 40;
        }
        $margin = $basemargin;
        return  $margin; 
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
        $widget = $this->getWidget();
        $firstSeries = $widget->getSeries()[0];
        $position = $widget->getLegendPosition();
        if ($position === null && $firstSeries instanceof PieChartSeries) {
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
        if ($firstSeries instanceof PieChartSeries){            
            $padding = 'padding: [20,10,20,10],';
        }
        
        if ($this->legendHidden() === true) {
            $show = 'show: false,';
        } else {
            $show = '';
        }
        return <<<JS

{
	type: 'scroll',
    {$show}
    {$padding}    
    {$positionJs}

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
    
    public function buildJsValueGetter($column = null, $row = null)
    {
        if ($column != null) {
            $key = $column;
        } else {
            if ($this->getWidget()->hasUidColumn() === true) {
                $column = $this->getWidget()->getUidColumn()->getDataColumn();
            } else {
                throw new FacadeOutputError('Cannot create a value getter for a data widget without a UID column: either specify a column to get the value from or a UID column for the table.');
            }
        }
        if ($row != null) {
            throw new FacadeOutputError('Unsupported function ');
        }
        
        
        
        return <<<JS

                function(){
                    var data = '';
                    var selectedRow = {$this->buildJsEChartsVar()}._oldSelection;
                    if (selectedRow && '{$key}' in selectedRow) {
                        data = selectedRow["{$key}"];
                    }
                return data;
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
        return "{$this->buildJsEChartsVar()}.setOption({}, true);";
    }
    
    protected function legendHidden() : bool
    {
        $widget = $this->getWidget();
        $firstSeries = $widget->getSeries()[0];
        if (count($widget->getSeries()) == 1 && ($firstSeries instanceof PieChartSeries) === false) {
            if ($firstSeries->getValueDataColumn() == $firstSeries->getValueAxis()->getDataColumn()){
                return true;
            } else {
                return false;
            }
        }
        return false;
    }
    
}