<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iReadData;
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
    
    /**
     * Function to build the div element forthe chart
     *
     * @param string $style
     * @return string
     */
    protected function buildHtmlChart($style = 'height:100%; min-height: 100px; overflow: hidden;') : string
    {
        return '<div id="' . $this->getId() . '" style="' . $style . '"></div>';
    }
    
    /**
     * Build the necessary script includes for the charts
     *
     * @return array
     */
    protected function buildHtmlHeadDefaultIncludes() : array
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
    
    /**
     * Build the javascript function
     *
     * @return string
     */
    protected function buildJsFunctions()
    {
        return <<<JS
        
    // Create the load function to fetch the data via AJAX or from another widget
    function {$this->buildJsDataLoadFunctionName()}() {
        {$this->buildJsDataLoadFunctionBody()}
    };
    
    //Create the redraw function for the chart
    function {$this->buildJsRedrawFunctionName()}(oData) {
        {$this->buildJsRedrawFunctionBody('oData')}
    };
    
    //Create the select function
    function {$this->buildJsSelectFunctionName()}(selection) {
        {$this->buildJsSelectFunctionBody('selection')}
    };
    
    function {$this->buildJsSingleClickFunctionName()}(params) {
        {$this->buildJsSingleClickFunctionBody('params')}
    };
    
    function {$this->buildJsClicksFunctionName()}(params) {
        {$this->buildJsClicksFunctionBody('params')}
    };
    
JS;
    }
    
    /**
     * function to return javascript eventhandler functions
     *
     * @return string
     */
    protected function buildJsEventHandlers() : string
    {
        $handlersJs = '';
        $handlersJs = $this->buildJsLegendSelectHandler();
        $handlersJs .= $this->buildJsOnClickHandler();
        $handlersJs .= $this->buildJsOnDoubleClickHandler();
        return $handlersJs;
    }
    
    /**
     *
     * @param string $dataJs
     * @return string
     */
    protected function buildJsRedraw(string $dataJs) : string
    {
        return $this->buildJsRedrawFunctionName(). '(' . $dataJs . ')';
    }
    
    /**
     * javascript function name for function that gets called when the chart should be redrawn,
     * e.g. when data got successfully loaded by ajax request
     *
     * @return string
     */
    protected function buildJsRedrawFunctionName() : string
    {
        return $this->buildJsFunctionPrefix() . 'redraw';
    }
    
    /**
     * Function to refresh the chart
     *
     * @return string
     */
    public function buildJsRefresh() : string
    {
        return $this->buildJsDataLoadFunctionName() . '();';
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsDataLoadFunctionName() : string
    {
        return $this->buildJsFunctionPrefix() . 'load';
    }
    
    /**
     * Function to load the data for the chart, needs to be implemtened in the facade chart element
     *
     * @return string
     */
    abstract protected function buildJsDataLoadFunctionBody() : string;
    
    /**
     * function to initalize echart, optional it's possible to set a theme
     *
     * @param string $theme
     * @return string
     */
    public function buildJsEChartsInit(string $theme = null) : string
    {
        return <<<JS
        
    var {$this->buildJsEChartsVar()} = echarts.init(document.getElementById('{$this->getId()}'), '{$theme}');
    
JS;
    }
    
    /**
     *
     * @param string $oRowJs
     * @return string
     */
    protected function buildJsSelect(string $oRowJs = '') : string
    {
        return $this->buildJsSelectFunctionName() . '(' . $oRowJs . ')';
    }
    
    /**
     * Javascript function name for js function that gets called when a data point in the chart gets selected
     *
     * @return string
     */
    protected function buildJsSelectFunctionName() : string
    {
        return $this->buildJsFunctionPrefix() . 'select';
    }
    
    /**
     * Body for the javascript function that gets called when series data point gets selected
     *
     * @param string $selection
     * @return string
     */
    protected function buildJsSelectFunctionBody(string $selection) : string
    {
        return <<<JS
            var echart = {$this->buildJsEChartsVar()};
            var oSelectedRow = {$selection};
            if (echart._oldselection === undefined) {
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
    
    /**
     * 
     * @param string $params
     * @return string
     */
    protected function buildJsClicks(string $params = '') : string
    {
        return $this->buildJsClicksFunctionName() . '(' . $params . ')';
    }
    
    /**
     * Function name for javascript function that evalutes clicks on a chart
     * 
     * @return string
     */
    protected function buildJsClicksFunctionName() : string
    {
        return $this->buildJsFunctionPrefix() . 'clicks';
    }
    
    /**
     * Javascript function body for function that evaluates if a click on a chart was a single click or a double click,
     * if it was a single click the single click function is called
     * 
     * @param string $params
     * @return string
     */
    protected function buildJsClicksFunctionBody(string $params) : string
    {
        return <<<JS

            var clickCount = {$this->buildJsEChartsVar()}._clickCount
            
            clickCount++;
            {$this->buildJsEChartsVar()}._clickCount = clickCount
            if (clickCount == 1) {
                setTimeout(function(){
                    if(clickCount == 1) {                        
                        // Single click code, or invoke a function
                        {$this->buildJsSingleClick($params)}
                    } else {
                        // Double click code, or invoke a function
                    }
                    clickCount = 0;
                    {$this->buildJsEChartsVar()}._clickCount = clickCount
                }, 500);
            }
            
JS;
                        
    }
    
    /**
     * javascript to compare if two data rows are equal
     *
     * @param string $leftRowJs
     * @param string $rightRowJs
     * @return string
     */
    protected function buildJsRowCompare(string $leftRowJs, string $rightRowJs) : string
    {
        return "(JSON.stringify({$leftRowJs}) == JSON.stringify({$rightRowJs}))";
    }
    
    /**
     * javascript function to handle clicks on the chart
     *
     * @return string
     */
    protected function buildJsOnClickHandler() : string
    {
        return <<<JS
        
        {$this->buildJsEChartsVar()}.on('click', function(params){
            {$this->buildJsClicks('params')}
    });
    
JS;
    }
    
    /**
     * javascript function handling legend select changes
     * moves shown markLine to a still visible chart series on the same axis
     *
     * @return string
     */
    protected function buildJsLegendSelectHandler() : string
    {
        return <<<JS
        
        {$this->buildJsEChartsVar()}.on('legendselectchanged', function(params){
            var options = {$this->buildJsEChartsVar()}.getOption();
            //Check if series gets hidden, if not (means getting shown) do nothing
            if (params.selected[params.name] === false) {
                if (options.series[0].seriesType === 'pie') {
                    //do nothing
                } else {
                    var newOptions = {series: []};
                    var markLineSet = false;
                    var markLineData;
                    var axisIndex;
                    options.series.forEach((series) => {
                        //check if the series that gets hidden was showing markLine
                        //if so, save markLine.data and the axisIndex of series
                        if (params.name === series.name && series.markLine._show === true) {
                            markLineData = series.markLine.data;
                            if (series._bar === true) {
                                axisIndex = series.yAxisIndex;
                            } else {
                                axisIndex = series.xAxisIndex;
                            }
                        }
                    });
                    options.series.forEach((series) => {
                        //check if series is shown, if no markLine is set yet
                        //and if markLineData was saved, means if a markLine was show on the
                        //series that got hidden
                        if (params.selected[series.name] === true && markLineSet === false && markLineData !== undefined ) {
                            if ((series._bar === true && series.yAxisIndex === axisIndex) || (series._bar === undefined && series.xAxisIndex === axisIndex)) {
                                newOptions.series.push({markLine: {data: markLineData, _show: true}});
                                markLineSet = true;
                            }
                        //check if series already shows markLine and if its not hidden
                        } else if(params.selected[series.name] === true && series.markLine._show === true) {
                            newOptions.series.push(series);
                            markLineSet = true;
                        //if none of the above checks succeed
                        } else {
                            newOptions.series.push({markLine: {data: {}, _show: false}});
                        }
                    });
                    {$this->buildJsEChartsVar()}.setOption(newOptions);
                    //when no series left that can show the markLine set selected data empty
                    if (markLineSet === false) {
                        {$this->buildJsSelect()}
                    }
                }
            }
        });
        
JS;
                        
    }
    
    /**
     * Javascript function name for function that handles a single click on a chart
     * 
     * @return string
     */
    protected function buildJsSingleClickFunctionName() : string
    {
        return $this->buildJsFunctionPrefix() . 'singleClick';
    }
    
    protected function buildJsSingleClick(string $params = '') : string
    {
        return $this->buildJsSingleClickFunctionName() . '(' . $params . ')';
    }
    
    /**
     * Javascript function body for function that handles a single click on a chart
     * 
     * @param string $params
     * @return string
     */
    protected function buildJsSingleClickFunctionBody(string $params) : string
    {
        return <<<JS
        
        var params = {$params}
        var dataRow = params.data
        if (params.seriesType == 'pie') {
            if ((typeof {$this->buildJsEChartsVar()}._oldSelection != undefined) && ({$this->buildJsRowCompare($this->buildJsEChartsVar() . '._oldSelection', 'dataRow')}) == true) {
                {$this->buildJsEChartsVar()}.dispatchAction({
                    type: 'pieUnSelect',
                    seriesIndex: params.seriesIndex,
                    dataIndex: params.dataIndex
                });
                {$this->buildJsSelect()}
            } else {
                if ({$this->buildJsEChartsVar()}._oldSelection != undefined) {
                    {$this->buildJsEChartsVar()}.dispatchAction({
                        type: 'pieUnSelect',
                        seriesIndex: params.seriesIndex,
                        name: {$this->buildJsEChartsVar()}._oldSelection.name
                    });
                }
                {$this->buildJsEChartsVar()}.dispatchAction({
                    type: 'pieSelect',
                    seriesIndex: params.seriesIndex,
                    dataIndex: params.dataIndex
                });
                {$this->buildJsSelect('dataRow')}
            }
        } else {
            var options = {$this->buildJsEChartsVar()}.getOption();
            var newOptions = {series: []};
            var sameValue = false;
            options.series.forEach((series) => {
                newOptions.series.push({markLine: {data: {}}, _show: false});
            });
            if ((typeof {$this->buildJsEChartsVar()}._oldSelection != undefined) && ({$this->buildJsRowCompare($this->buildJsEChartsVar() . '._oldSelection', 'dataRow')}) == true) {
                {$this->buildJsSelect()}
            } else {
                if (("_bar" in options.series[params.seriesIndex]) == true) {
                    newOptions.series[params.seriesIndex].markLine.data = [
                        {
            				yAxis: dataRow[options.series[params.seriesIndex].encode.y]
            			}
                    ];
                    newOptions.series[params.seriesIndex].markLine._show = true;
                } else {
                    newOptions.series[params.seriesIndex].markLine.data = [
                        {
            				xAxis: dataRow[options.series[params.seriesIndex].encode.x]
            			}
                    ];
                    newOptions.series[params.seriesIndex].markLine._show = true;
                }
                {$this->buildJsSelect('dataRow')}
            }
            {$this->buildJsEChartsVar()}.setOption(newOptions);
        }
        
JS;
    }
    
    /**
     * DoubleClickHandler, implementation for EasyUI Facade, other Facades probably have to overwritte this function with their facade specific implementation
     * 
     * @return string
     */
    protected function buildJsOnDoubleClickHandler() : string
    {
        $widget = $this->getWidget();
        $output = '';
        
        // Double click actions. Currently only supports one double click action - the first one in the list of buttons
        if ($dblclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_DOUBLE_CLICK)[0]) {
            $output .= <<<JS
            
            {$this->buildJsEChartsVar()}.on('dblclick', function(params){
                {$this->buildJsEChartsVar()}._oldSelection =  params.data
                {$this->getFacade()->getElement($dblclick_button)->buildJsClickFunction()}
            });
            
JS;
                
        }
        return $output;
    }
    
    /**
     * function to build javascript for the base config of the chart (tooltip, legend, series, axis)
     *
     * @throws FacadeUnsupportedWidgetPropertyWarning
     * @return string
     */
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
	series: [{$seriesConfig}],
    {$this->buildJsAxes()}
    
}

JS;
    }
    
    /**
     * function to select what kind of series, choosing the right configuration for the series
     *
     * @param ChartSeries $series
     * @return string
     */
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
    
    /**
     * build line series configuration
     *
     * @param LineChartSeries $series
     * @return string
     */
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
    _index: {$series->getIndex()},
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
    {$this->buildJsMarkLineProperties($series)}
},

JS;
    }
    
    /**
     * build configuration that is equal for bar and column series
     *
     * @param ColumnChartSeries $series
     * @return string
     */
    protected function buildJsColumnBarChartProperties (ColumnChartSeries $series) :string
    {
        if ($series->getColor() !== null) {
            $color = "itemStyle: { color: '{$series->getColor()}' },";
        } else {
            $color = '';
        }
        
        return <<<JS
        
    name: '{$series->getCaption()}',
    _index: {$series->getIndex()},
    type: 'bar',
    encode: {
        x: '{$series->getXDataColumn()->getDataColumnName()}',
        y: '{$series->getYDataColumn()->getDataColumnName()}'
    },
    xAxisIndex: {$series->getXAxis()->getIndex()},
    yAxisIndex: {$series->getYAxis()->getIndex()},
    {$color}
    {$this->buildJsStack($series)}
    {$this->buildJsMarkLineProperties($series)}
    
JS;
    }
    
    /**
     * build column series configuration
     *
     * @param ColumnChartSeries $series
     * @return string
     */
    protected function buildJsColumnChart(ColumnChartSeries $series) : string
    {
        return <<<JS
        
{
{$this->buildJsColumnBarChartProperties($series)}
},

JS;
    }
    
    /**
     * build bar series configuration
     *
     * @param BarChartSeries $series
     * @return string
     */
    protected function buildJsBarChart(BarChartSeries $series) : string
    {
        return <<<JS
        
{
{$this->buildJsColumnBarChartProperties($series)}
    _bar: true
},

JS;
    }
    
    /**
     * build stack options configuration
     *
     * @param StackableChartSeriesInterface $series
     * @return string
     */
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
    
    /**
     * build rose series configuration
     *
     * @param RoseChartSeries $series
     * @return string
     */
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
    
    /**
     * build pie series configuration
     *
     * @param PieChartSeries $series
     * @return string
     */
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
    //selectedMode: 'single',
    animationType: 'scale',
    animationEasing: 'backOut',
    
},

JS;
    }
    
    /**
     * build axes configuration
     *
     * @return string
     */
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
    
    /**
     * build axis properties
     *
     * @param ChartAxis $axis
     * @param int $nameGapMulti
     * @return string
     */
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
        axisPointer: {
            label: {
                formatter: function(params) {
                return {$this->buildJsLabelFormatter($axis->getDataColumn(), 'params.value')}
                },
            },
        },
        {$min}
        {$max}
    },
    
JS;
    }
    
    /**
     * build axis zoom configuration
     *
     * @param ChartAxis $axis
     * @return string
     */
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
    
    /**
     * build basic markLine configuration
     *
     * @param ChartSeries $series
     * @return string
     */
    protected function buildJsMarkLineProperties(ChartSeries $series) : string
    {
        if ($series instanceof BarChartSeries) {
            $position = "position: 'middle',";
            $formatter = $this->buildJsLabelFormatter($series->getYDataColumn(), 'params.value');
        } else {
            $position = '';
            $formatter = $this->buildJsLabelFormatter($series->getXDataColumn(), 'params.value');
        }
        return <<<JS
        
    markLine: {
        _show: false,
        data: [],
        silent: true,
        symbol: 'circle',
        animation: false,
        label: {
            show: true,
            {$position}
            formatter: function(params) {
                return {$formatter}
            }
        },
        lineStyle: {
            color: '#000',
            type: 'solid',
        }
    },
    
JS;
    }
    
    /**
     * build basic MarkArea configuration (MarkAreas are not used yet)
     * 
     * @param ChartSeries $series
     * @return string
     */
    protected function buildJsMarkAreaProperties(ChartSeries $series) : string
    {
        
        return <<<JS
        
    markArea: {
        _show: false,
        data: [],
        silent: true,
        symbol: 'circle',
        animation: false,
        label: {
            show: true,
            color: '#000'
            formatter: function(params) {
                return {$this->buildJsLabelFormatter($series->getXDataColumn(), 'params.value')}
            }
        },
        itemStyle: {
            color: '#000',
            bordertype: 'solid',
            
        }
    },
    
JS;
    }
    
    /**
     * build formatter for axis labels
     *
     * @param DataColumn $col
     * @param string $js_var_value
     * @return string
     */
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
    
    /**
     * basic gap between an axis and it's name
     *
     * @return int
     */
    protected function baseAxisNameGap() : int
    {
        return 15;
    }
    
    /**
     * javascript function body to analyse data and calculate axis/grid offsets and draw the chart
     *
     * @param string $dataJs
     * @return string
     */
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
                    $offset = 'len * 9';
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
                if ($axis->getHideCaption() === false) {
                    $offset = strlen($axis->getCaption())*3.5;
                } else {
                    $offset = 0;
                }
                $axesJsObjectInit .= <<<JS
                
    axes["{$axis->getDataColumn()->getDataColumnName()}"] = {
        offset: {$offset},
        dimension: "{$axis->getDimension()}",
        position: "{$postion}",
        index: "{$axis->getIndex()}",
        name: "{$axis->getDataColumn()->getDataColumnName()}",
    };
    
JS;
            }
            
            $js = <<<JS
            
    //var longestString = 0;
    
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
    //newOptions.dataset = {source: rowData};
    
    {$this->buildJsEChartsVar()}.setOption(newOptions);
    
    var split = "{$this->getWidget()->getSeries()[0]->getSplitByAttributeAlias()}" || undefined
    if (split === undefined) {
        {$this->buildJsEChartsVar()}.setOption({dataset: {source: rowData}})
    } else {
        {$this->buildJsSplitSeries()}
        
    }
    
JS;
        }
        
        return <<<JS
        
    var rowData = $dataJs;
    if (! rowData || rowData.count === 0) {
        {$this->buildJsDataResetter()}
        {$this->buildJsMessageOverlayShow($this->getWidget()->getEmptyText())}
        return
    }
    var echart = {$this->buildJsEChartsVar()}
    echart._dataset = rowData
    echart._oldSelection = undefined
    echart._clickCount = 0
{$this->buildJsMessageOverlayHide()}
{$this->buildJsEChartsVar()}.setOption({$this->buildJsChartConfig()})
$js

JS;
    }
    
    /**
     * function to split the dataset and configure series for each split (not used yet)
     *
     * @return string
     */
    protected function buildJsSplitSeries() : string
    {
        //TODO
        return <<<JS
    console.log(split)
    var grouped = {};
        for (var i=0; i<rowData.length; i++) {
        var p = rowData[i][split];
        console.log(p)
        if (!grouped[p]) { grouped[p] = []; }
        grouped[p].push(rowData[i]);
    }
    console.log(grouped)
    
    
JS;
    }
    
    /**
     * function to build overlay and show given message
     *
     * @param string $message
     * @return string
     */
    protected function buildJsMessageOverlayShow(string $message) : string
    {
        return <<<JS
        
$({$this->buildJsEChartsVar()}.getDom()).prepend($('<div class="exf-chart-message" style="position: absolute; padding: 10px; width: 100%; text-align: center;">{$message}</div>'));

JS;
    }
    
    /**
     * function to hide overlay message
     *
     * @return string
     */
    protected function buildJsMessageOverlayHide() : string
    {
        return <<<JS
if ($(".exf-chart-message")[0]) {
    $(".exf-chart-message").remove();
}

JS;
        
    }
    
    /**
     * calculate the basic grid top margin
     *
     * @return int
     */
    protected function buildJsGridMarginTop() : int
    {
        $baseMargin = 20;
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
            $margin += 30;
        }
        return $baseMargin + $margin;
    }
    
    /**
     * calculate the basic grid right margin
     *
     * @return int
     */
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
    
    /**
     * calculate the basic grid bottom margin
     *
     * @return int
     */
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
    
    /**
     * calculate the basic grid left margin
     *
     * @return int
     */
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
    
    /**
     * function to check if series is a pie series
     *
     * @return bool
     */
    protected function isPieChartSeries() : bool
    {
        if ($this->getWidget()->getSeries()[0] instanceof PieChartSeries || $this->getWidget()->getSeries()[0] instanceof DonutChartSeries) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * build basic tooltip configuration
     *
     * @return string
     */
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
	},
    /*formatter: (params) => {
        //console.log(params)
        var options = {$this->buildJsEChartsVar()}.getOption();
        let tooltip = params[0].axisValueLabel + '<br/>';
        params.forEach(({marker, seriesName, value, seriesIndex}) => {
            //console.log(options)
            if (("_bar" in options.series[seriesIndex]) == true) {
                data = options.series[seriesIndex].encode.x;
            } else {
                data = options.series[seriesIndex].encode.y;
            }
            //console.log(data);
            tooltip += marker +' ' + seriesName + ': ' + value[data] + '<br/>';
        });
        return tooltip;
    },*/
/*formatter: function(params) {
    console.log(params)
},*/
},

JS;
        }
    }
    
    /**
     * build basic legend configuration
     *
     * @return string
     */
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
    
    /**
     * build the chart element id
     *
     * @return string
     */
    protected function buildJsEChartsVar() : string
    {
        return "chart_{$this->getId()}";
    }
    
    /**
     * build function thats calles when chart gets resized
     *
     * @return string
     */
    protected function buildJsEChartsResize() : string
    {
        return "{$this->buildJsEChartsVar()}.resize()";
    }
    
    /**
     * build function to get value of a selected data row
     *
     * @param unknown $column
     * @param unknown $row
     * @throws FacadeOutputError
     * @return string
     */
    public function buildJsValueGetter($column = null, $row = null)
    {
        if ($column != null) {
            $key = $column;
        } else {
            if ($this->getWidget()->getData()->hasUidColumn() === true) {
                $column = $this->getWidget()->getData()->getUidColumn();
                $key = $column->getDataColumnName();
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
     *
     * {@inheritDoc}
     * @see exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        $widget = $this->getWidget();
        $rows = '';
        if (is_null($action)) {
            $rows = "{$this->buildJsEChartsVar()}._dataset";
        } elseif ($action instanceof iReadData) {
            // If we are reading, than we need the special data from the configurator
            // widget: filters, sorters, etc.
            return $this->getFacade()->getElement($widget->getConfiguratorWidget())->buildJsDataGetter($action);
        } else {
            if ($this->getWidget()->getSeries()[0] instanceof PieChartSeries) {
                //return "console.log({$this->buildJsEChartsVar()}._oldSelection, {$this->buildJsEChartsVar()}._dataset )";
                $rows = <<<JS
                
                    function(){
                        var dataset = {$this->buildJsEChartsVar()}._dataset;
                        var selectedRow = {$this->buildJsEChartsVar()}._oldSelection;
                        for (var i = 0; i < dataset.length; i++) {
                            if (dataset[i].{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()} === selectedRow.name) {
                                return [dataset[i]]
                            }
                        }
                    }()
                    
JS;
            } else {
                $rows = "[{$this->buildJsEChartsVar()}._oldSelection]";
            }
        }
        return "{oId: '" . $widget->getMetaObject()->getId() . "'" . ($rows ? ", rows: " . $rows : '') . "}";
    }
    
    
    /**
     * Returns a JS snippet, that empties the chart.
     *
     * @return string
     */
    protected function buildJsDataResetter() : string
    {
        return <<<JS
var echart = {$this->buildJsEChartsVar()};
{$this->buildJsEChartsVar()}.setOption({}, true);
echart._oldSelection = undefined

JS;

    }
    
    /**
     * function to check if legend should be hidden
     *
     * @return bool
     */
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