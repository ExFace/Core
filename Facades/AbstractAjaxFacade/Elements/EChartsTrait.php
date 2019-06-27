<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\Interfaces\Widgets\iDisplayValue;
use exface\Core\CommonLogic\UxonObject;
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
use exface\Core\Widgets\Parts\Charts\GraphChartSeries;
use exface\Core\Widgets\DataButton;

/**
 *
 * @method Chart getWidget()
 * @author rml
 *
 */
trait EChartsTrait
{
    private $chartTypeButtonGroup = null;
    
    /**
     * 
     * @return string
     */
    protected function buildJsLiveReference() : string
    {
        $output = '';
        if ($link = $this->getWidget()->getDataWidgetLink()) {
            $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
            $output .= $this->buildJsRedraw($linked_element->buildJsDataGetter().'.rows');
        }
        return $output;
    }
    
    /**
     * 
     * @return \exface\Core\Facades\AbstractAjaxFacade\Elements\EChartsTrait
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
    
    /**
     * Function to build the div element forthe chart
     *
     * @param string $style
     * @return string
     */
    protected function buildHtmlChart($style = 'height:100%; min-height: 100px; overflow: hidden;') : string
    {
        return '<div id="' . $this->getId() . '_echarts" style="' . $style . '"></div>';
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
    
    protected function addChartButtons() : void
    {
        $buttonTemplate = new UxonObject([
            'widget_type' => 'DataButton',
            'action' => [
                'alias' => 'exface.Core.CustomFacadeScript',
                'script' => ''
            ],
            'align' => 'right',
            'hide_caption' => true
        ]);
        
        $widget = $this->getWidget();
        $tb = $widget->getToolbarMain();
        /*$chartTypeBtnGroup = $tb->createButtonGroup();
        $this->chartTypeButtonGroup = $chartTypeBtnGroup;
        $tb->addButtonGroup($chartTypeBtnGroup, $tb->getButtonGroupIndex($tb->getButtonGroupForSearchActions()));
        */
        /* @var \exface\Core\Widgets\Button $menu */
        $menu = WidgetFactory::createFromUxonInParent($widget, new UxonObject([
            'widget_type' => 'MenuButton',
            'icon' => 'line-chart',
            'hide_caption' => true,
            'caption' => 'Chart type',
            'hint' => 'Change chart type'
        ]));
        $tb->getButtonGroupForSearchActions()->addButton($menu, 1);
        if ($this->isGraphChart() === true) {
            $buttonUxon = $buttonTemplate->copy();
            $buttonUxon->setProperty('caption', 'Circle');            
            $buttonUxon->setProperty('icon', 'circle-o');
            $button = WidgetFactory::createFromUxon($widget->getPage(), $buttonUxon, $menu);
            $button->getAction()->setScript($this->buildJsChangeToCircleGraph($button));
            //$chartTypeBtnGroup->addButton($button,1);
            $menu->addButton($button);
            
            $buttonUxon = $buttonTemplate->copy();
            $buttonUxon->setProperty('caption', 'Network');
            $buttonUxon->setProperty('icon', 'share-alt');
            $button = WidgetFactory::createFromUxon($widget->getPage(), $buttonUxon, $menu);
            $button->getAction()->setScript($this->buildJsChangeToNetworkGraph($button));
            //$chartTypeBtnGroup->addButton($button,1);
            $menu->addButton($button);
        }
        return;
    }
    
    /**
     * Build the javascript function
     *
     * @return string
     */
    protected function buildJsFunctions() : string
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
    
    //Create the single click function
    function {$this->buildJsSingleClickFunctionName()}(params) {
        {$this->buildJsSingleClickFunctionBody('params')}
    };
    
    //create the clicks function to distinguish between single and double click
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
        $handlersJs .= $this->buildJsBindToClickHandler();
        if ($this->isGraphChart() === true) {
            $handlersJs .= $this->buildJsOnGraphHoverHandler();
        }
        return $handlersJs;
    }
    
    /**
     * js script for to change grapt to a circle graph
     * 
     * @param DataButton $button
     * @return string
     */
    protected function buildJsChangeToCircleGraph(DataButton $button) : string
    {
        return <<<JS

            var echart = {$this->buildJsEChartsVar()};
            var options= {};
            options.series = {
                layout: 'circular',
                lineStyle: {
                    curveness: 0.2
                },
            };
            echart.setOption(options);
            echart.resize();

JS;
    }
            
    /**
     * js script for to change grapt to a network graph
     *
     * @param DataButton $button
     * @return string
     */
    protected function buildJsChangeToNetworkGraph(DataButton $button) : string
    {
        // only works when the initial graph was a network graph
        //TODO Chart zoom, damit nodes und edged connected (Bug, sind verschoben) 
        return <<<JS
        
            var echart = {$this->buildJsEChartsVar()};
            var options = {};
            /*options.series = {$this->buildJsGraphChart($this->getWidget()->getSeries()[0])};*/
            {$this->buildJsRefresh()}
            /*
            options.series = {
                layout: 'force',
                lineStyle: {
                    curveness: 0
                },
            };
            
            echart.setOption(options);
            */
            /*
            var elm = document.getElementById('{$this->buildJsEChartsDivVar()}').getElementsByTagName('canvas')[0];
            var evt = document.createEvent("MouseEvents");
            evt.initEvent('mousewheel', true, true);
            evt.wheelDelta = 120;
            elm.dispatchEvent(evt);
            */
            
JS;
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
        
    var {$this->buildJsEChartsVar()} = echarts.init(document.getElementById('{$this->buildJsEChartsDivVar()}'), '{$theme}');
    
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
            var oSelected = {$selection};
            if (echart._oldselection === undefined) {
                echart._oldSelection = {$selection};
            } else {
                if (({$this->buildJsRowCompare('echart._oldSelection', 'oSelected')}) === false) {
                    echart._oldSelection = {$selection};
                } else {
                    return;
                }
            }
            {$this->getOnChangeScript()}
            
JS;
    }
            
            
    /**
     * returns the data row from the initial dataset for a selection on a graph
     * 
     * @param string $selection
     * @return string
     */
    protected function buildJsGetSelectedRowFunction(string $selection) : string
    {
        if ($this->isPieChart() === true) {
            return <<<JS
            
                    function(){
                        var dataset = {$this->buildJsEChartsVar()}._dataset;
                        var selectedRow = {$selection};
                        for (var i = 0; i < dataset.length; i++) {
                            if (dataset[i].{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()} === selectedRow.name) {
                                return dataset[i];
                            }
                        }
                        return '';
                    }()
                    
JS;
            
        } else if ($this->isGraphChart() === true) {
            return <<<JS
            
                    function(){
                        var dataset = {$this->buildJsEChartsVar()}._dataset;
                        var selection = {$selection};
                        // searches first if a left object UID matches the data.id
                        for (var i = 0; i < dataset.length; i++) {
                            if (dataset[i].{$this->getWidget()->getSeries()[0]->getLeftObjectDataColumn()->getDataColumnName()} === selection.id) {
                                return dataset[i];
                            }
                        }/*
                        // if no node matches, then searches if a relation UID matches the data.id
                        for (var i = 0; i < dataset.length; i++) {
                            if (dataset[i].{$this->getWidget()->getSeries()[0]->getRelationDataColumn()->getDataColumnName()} === selection.id) {
                                return dataset[i];
                            }
                        }*/
                        return '';
                    }()
                    
JS;
            
        } else {
            return "{$selection}";
        }
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

            var clickCount = {$this->buildJsEChartsVar()}._clickCount;
            var params = {$params};
            var selected = {$this->buildJsGetSelectedRowFunction('params.data')};
            
            clickCount++;
            {$this->buildJsEChartsVar()}._clickCount = clickCount;
            if (clickCount === 1) {
                if ({$this->buildJsEChartsVar()}._oldSelection === undefined || {$this->buildJsEChartsVar()}._oldSelection != selected ) {
                    {$this->buildJsEChartsVar()}._doubleClkSelection = selected;
                }
                {$this->buildJsSingleClick($params)} 
                setTimeout(function(){
                    clickCount = 0;
                    {$this->buildJsEChartsVar()}._clickCount = clickCount;
                    {$this->buildJsEChartsVar()}._doubleClkSelection = undefined;
                }, 500);
            } else {
                if ({$this->buildJsEChartsVar()}._doubleClkSelection != undefined) {
                    // do nothing
                } else {                        
                    {$this->buildJsSingleClick($params)}
                }
                
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
            console.log(params)
            {$this->buildJsClicks('params')}
        });
    
JS;
    }
            
    /**
     * javascript function to handle hover behavior for graph charts
     * when a node was selected and mouse moves over other nodes or not hovers anything,
     * the node still stays selected
     * 
     * @return string
     */
    protected function buildJsOnGraphHoverHandler() : string
    {
        return <<<JS

        {$this->buildJsEChartsVar()}.on('unfocusnodeadjacency', function(params){
            var echart = {$this->buildJsEChartsVar()};
            if (echart._oldSelection != undefined) {
                var selection = echart._oldSelection
                var options = echart.getOption();
                var nodes = options.series[0].data
                var index = function(){
                    for (var i = 0; i < nodes.length; i++) {
                        if (nodes[i].id === selection.{$this->getWidget()->getSeries()[0]->getLeftObjectDataColumn()->getDataColumnName()}) {
                            return i
                        }
                    }                    
                }()
                {$this->buildJsCallEChartsAction('echart', 'focusNodeAdjacency', '0', 'index')}
            }
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
                        } else if (params.selected[series.name] === true && series.markLine._show === true) {
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
    
   /**
    * 
    * @param string $params
    * @return string
    */
    protected function buildJsSingleClick(string $params = '') : string
    {
        return $this->buildJsSingleClickFunctionName() . '(' . $params . ')';
    }
    
    /**
     * javascript snippet to call an echarts action
     * 
     * @param string $chart
     * @param string $type
     * @param string $seriesIndexJs
     * @param string $dataIndexJs
     * @param string $nameJs
     * @return string
     */
    protected function buildJsCallEChartsAction(string $chart, string $type, string $seriesIndexJs, string $dataIndexJs = null, string $nameJs = null) : string
    {
        if ($dataIndexJs != null) {
            $dataIndex = "dataIndex: {$dataIndexJs},";
        } else {
            $dataIndex = '';
        }
        if ($nameJs != null) {
            $name = "name: {$nameJs},";
        } else {
            $name = '';
        }
        return <<<JS
        
    {$chart}.dispatchAction({
        type: '{$type}',
        seriesIndex: {$seriesIndexJs},
        {$dataIndex}
        {$name}
    });

JS;
    }
    /**
     * Javascript function body for function that handles a single click on a chart
     * 
     * @param string $params
     * @return string
     */
    protected function buildJsSingleClickFunctionBody(string $params) : string
    {
        if ($this->isPieChart() === true) {
            return <<<JS
            
        var params = {$params};
        var dataRow = {$this->buildJsGetSelectedRowFunction('params.data')};
        var echart = {$this->buildJsEChartsVar()};
        // if already a pie part is selected do the following
        if (echart._oldSelection != undefined) {
            // if already slected piepart gets clicked again
            if ({$this->buildJsRowCompare('echart._oldSelection', 'dataRow')} == true) {
                // deselect the pie part
                {$this->buildJsCallEChartsAction('echart', 'pieUnSelect', 'params.seriesIndex', 'params.dataIndex')}
                {$this->buildJsSelect()}
            // if different part then already selected part gets clicked
            } else {
                // deselect old pie part
                var name = echart._oldSelection.{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()}
                {$this->buildJsCallEChartsAction('echart', 'pieUnSelect', 'params.seriesIndex', null, 'name')}
                // select clicked pie part
                {$this->buildJsCallEChartsAction('echart', 'pieSelect', 'params.seriesIndex', 'params.dataIndex')}
                {$this->buildJsSelect('dataRow')}
            }
        // if no pie part was selected
        } else {
            // select clicked pie part
            {$this->buildJsCallEChartsAction('echart', 'pieSelect', 'params.seriesIndex', 'params.dataIndex')}
            {$this->buildJsSelect('dataRow')}
        }

JS;
                
        } elseif ($this->isGraphChart() === true) {            
            return <<<JS

        
        var echart = {$this->buildJsEChartsVar()};
        var params = {$params};
        var dataRow = {$this->buildJsGetSelectedRowFunction('params.data')}; 
        if (params.dataType === "node") {          
            // if already a graph node part is selected do the following
            if (echart._oldSelection != undefined) {
                // if already selected graph node gets clicked again
                if ({$this->buildJsRowCompare('echart._oldSelection', 'dataRow')} == true) {
                    // deselected the node
                    {$this->buildJsCallEChartsAction('echart', 'unfocusNodeAdjacency', 'params.seriesIndex')}
                    {$this->buildJsSelect()}                        
                // if different node then already selected node gets clicked
                } else {
                    // deselect old node
                    {$this->buildJsCallEChartsAction('echart', 'unfocusNodeAdjacency', 'params.seriesIndex')}
                    // select clicked node 
                    {$this->buildJsCallEChartsAction('echart', 'focusNodeAdjacency', 'params.seriesIndex', 'params.dataIndex')}                       
                    {$this->buildJsSelect('dataRow')}
                }
            // if no node was selected
            } else {
                // select clicked node
                {$this->buildJsCallEChartsAction('echart', 'focusNodeAdjacency', 'params.seriesIndex', 'params.dataIndex')}
                {$this->buildJsSelect('dataRow')}
            }
        } else {
            if (echart._oldSelection != undefined) {
                {$this->buildJsCallEChartsAction('echart', 'unfocusNodeAdjacency', 'params.seriesIndex')}
                {$this->buildJsSelect()}
            }
        }

JS;
                
        } else {
            return <<<JS
        var echart = {$this->buildJsEChartsVar()};
        var params = {$params};
        var dataRow = {$this->buildJsGetSelectedRowFunction('params.data')};
        var options = echart.getOption();
        var newOptions = {series: []};
        options.series.forEach((series) => {
            newOptions.series.push({markLine: {data: {}, _show: false}});
        });
        // if the chart is a barchart
        if (("_bar" in options.series[params.seriesIndex]) == true) {
            newOptions.series[params.seriesIndex].markLine.data = [
                {
    				yAxis: dataRow[options.series[params.seriesIndex].encode.y]
    			}
            ];
            newOptions.series[params.seriesIndex].markLine._show = true;
        // if the chart is not a barchart
        } else {
            newOptions.series[params.seriesIndex].markLine.data = [
                {
    				xAxis: dataRow[options.series[params.seriesIndex].encode.x]
    			}
            ];
            newOptions.series[params.seriesIndex].markLine._show = true;
        }
        // if there was already a datapoint selected
        if (echart._oldSelection != undefined) {
            // if the selected datapoint is the same as the now clicked one
            if ({$this->buildJsRowCompare('echart._oldSelection', 'dataRow')} == true) {
                {$this->buildJsSelect()}
                newOptions = {series: []}
                options.series.forEach((series) => {                    
                    newOptions.series.push({markLine: {data: {}}, _show: false});
                });
            } else {
                {$this->buildJsSelect('dataRow')}
            }
        // if no datapoint was selected yet
        } else {
            {$this->buildJsSelect('dataRow')}
        }
        echart.setOption(newOptions);
    
JS;
        
        }
    }
    
    /**
     * Function to handle a double click on a chart, when a button is bound to double click
     * Implementation for EasyUI Facade, other Facades probably have to overwrite this function with
     * their facade specific implementation
     * 
     * @return string
     */
    protected function buildJsBindToClickHandler() : string
    {
        $widget = $this->getWidget();
        $output = '';
        
        if ($this->isGraphChart() === true) {
            // click actions for graph charts
            // for now you can only call an action when clicking on a node
            if ($dblclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_DOUBLE_CLICK)[0]) {
                $output .= <<<JS
                
            {$this->buildJsEChartsVar()}.on('dblclick', function(params){
                if (params.dataType === 'node') {
                    {$this->buildJsEChartsVar()}._oldSelection = {$this->buildJsGetSelectedRowFunction('params.data')};
                    {$this->getFacade()->getElement($dblclick_button)->buildJsClickFunction()}
                }
            });
            
JS;
                
            }
            /*if ($dblclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_DOUBLE_CLICK)[1]) {
                $output .= <<<JS
                
            {$this->buildJsEChartsVar()}.on('dblclick', {dataType: 'edge'}, function(params){
                {$this->buildJsEChartsVar()}._oldSelection = params.data
                {$this->getFacade()->getElement($dblclick_button)->buildJsClickFunction()}
            });
            
JS;
                
            }*/
            
            if ($rightclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_RIGHT_CLICK)[0]) {
                $output .= <<<JS
                
            {$this->buildJsEChartsVar()}.on('contextmenu', function(params){
                if (params.dataType === 'node') {
                    {$this->buildJsEChartsVar()}._oldSelection = {$this->buildJsGetSelectedRowFunction('params.data')};
                    {$this->getFacade()->getElement($rightclick_button)->buildJsClickFunction()}
                    params.event.event.preventDefault();
                }
            });

JS;
            }

            if ($leftclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_LEFT_CLICK)[0]) {
                $output .= <<<JS
                
            {$this->buildJsEChartsVar()}.on('click', function(params){
                if (params.dataType === 'node') {
                    {$this->buildJsEChartsVar()}._oldSelection = {$this->buildJsGetSelectedRowFunction('params.data')};
                    {$this->getFacade()->getElement($leftclick_button)->buildJsClickFunction()}
                }
            });

JS;
            }
            
        } else {
        
            // Double click actions for not graph charts
            // Currently only supports one double click action - the first one in the list of buttons
            if ($dblclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_DOUBLE_CLICK)[0]) {
                $output .= <<<JS
                
                {$this->buildJsEChartsVar()}.on('dblclick', function(params){
                    {$this->buildJsEChartsVar()}._oldSelection = {$this->buildJsGetSelectedRowFunction('params.data')};
                    {$this->getFacade()->getElement($dblclick_button)->buildJsClickFunction()}
                });
                
JS;
                    
            }
                    
            if ($leftclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_LEFT_CLICK)[0]) {
                $output .= <<<JS
                
                {$this->buildJsEChartsVar()}.on('click', function(params){
                    {$this->buildJsEChartsVar()}._oldSelection = {$this->buildJsGetSelectedRowFunction('params.data')};
                    {$this->getFacade()->getElement($leftclick_button)->buildJsClickFunction()}
                });
                
JS;
                    
            }
                    
            if ($rightclick_button = $widget->getButtonsBoundToMouseAction(EXF_MOUSE_ACTION_RIGHT_CLICK)[0]) {
                $output .= <<<JS
                
                {$this->buildJsEChartsVar()}.on('contextmenu', function(params){
                    {$this->buildJsEChartsVar()}._oldSelection = {$this->buildJsGetSelectedRowFunction('params.data')};
                    {$this->getFacade()->getElement($rightclick_button)->buildJsClickFunction()}
                    params.event.event.preventDefault();
                });
                
JS;
                    
            }
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
            if ($s instanceof GraphChartSeries && count($series) > 1) {
                throw new FacadeUnsupportedWidgetPropertyWarning('The facade "' . $this->getFacade()->getAlias() . '" does not support graph charts with multiple series!');
            }
            if (($s instanceof LineChartSeries || $s instanceof ColumnChartSeries) && count($series) > 1 && $s->getSplitByAttributeAlias() !== null) {
                throw new FacadeUnsupportedWidgetPropertyWarning('The facade "' . $this->getFacade()->getAlias() . '" does not support split by attribute with multiple series!');
            }
            $seriesConfig .= $this->buildJsChartSeriesConfig($s) . ',';
            
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
            case $series instanceof GraphChartSeries:
                return $this->buildJsGraphChart($series);                
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
        if ($series instanceof AreaChartSeries || $series->isFilled() === true) {
            if ($series->isFilled() === false) {
                $filledJs = '';
            } else {
                $filledJs = 'areaStyle: {},';
            }
        } else {
            $filledJs = '';
        }
        
        if ($series instanceof SplineChartSeries || $series->isSmooth() === true ) {
            if ($series->isSmooth() === false) {
                $smoothJs = '';
            } else {
                $smoothJs = 'smooth: true,';
            }
        } else {
            $smoothJs = '';
        }
        
        if ($series->getColor() !== null) {
            $color = <<<JS

    lineStyle: { color: '{$series->getColor()}' },
    itemStyle: { color: '{$series->getColor()}' },

JS;
            
        } else {
            $color = '';
        }
        
        if ($series->isSymbolHidden() === true) {
            $symbol = "showSymbol: false,";
        } else {
            $symbol = '';
        }
        
        if ($series->isStepline() === true) {
            $step = "step: 'end',";
        } else {
            $step = '';
        }
        
        return <<<JS
        
{
    name: '{$series->getCaption()}',
    _index: {$series->getIndex()},
    type: 'line',
    {$symbol}
    {$step}
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
}

JS;
    }
    
    /**
     * build configuration that is equal for bar and column series
     *
     * @param ColumnChartSeries $series
     * @return string
     */
    protected function buildJsColumnBarChartProperties (ColumnChartSeries $series) : string
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
}

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
}

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
        if ($series->isStacked() === true) {
            if ($series->getStackGroupId() !== null && !empty($series->getStackGroupId())) {
                $stack = "stack: '{$series->getStackGroupId()}',";
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
        if ($position !== null) {
            $label = '{show: false}';
        }
        if ($position == 'top' || $position == 'bottom' || $position == null) {
            $centerX = '50%';
        } elseif ($position == 'left') {
            $centerX = '70%';
        } elseif ($position == 'right') {
            $centerX = '30%';
        }
        
        $valueMode = $series->getValueMode();
        if ($valueMode == null) {
            $valueMode = '';
        } elseif ($valueMode == 'angle') {
            $valueMode = 'radius';
        } elseif ($valueMode == 'radius') {
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
    
}

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
        if ($position !== null) {
            $label = '{show: false}';
        }
        if ($position == 'top' || $position == 'bottom' || $position == null) {
            $centerX = '50%';
        } elseif ($position == 'left') {
            $centerX = '70%';
        } elseif ($position == 'right') {
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
    
}

JS;
    }
        
    /**
     * build graph series configuration
     *
     * @param GraphChartSeries $series
     * @return string
     */
    protected function buildJsGraphChart(GraphChartSeries $series) : string
    {        
        if ($series->getGraphType() === GraphChartSeries::GRAPH_TYPE_NETWORK) {
            $type = 'force';
            $curveness = '';
        } elseif ($series->getGraphType() === GraphChartSeries::GRAPH_TYPE_CIRCLE)  {
            $type = 'circular';
            $curveness = 'curveness: 0.2,';
        }
        
        if ($series->getColor() !== null) {
            $color = "{$series->getColor()}";
            
        } else {
            $color = '';
        }
        return <<<JS
        
{    
	height: '50%',
	name: 'Graph',
    type: 'graph',
	hoverAnimation: true,
	animationEasing: 'backOut',
	layout: '{$type}',
	edgeSymbol: ['none', 'arrow'],
	circular: { 
		rotateLabel: true,
	},
	force: {
		//initLayout: 'circular',
		gravity: 0.1,
		repulsion: 500,
		edgeLength: 120,
		layoutAnimation: false,
	}, 
    roam: true,
    focusNodeAdjacency: true,
    itemStyle: {     
        normal: {
            color: '{$color}',           
            borderColor: '#fff',
            borderWidth: 1,
        }
    },
    label: {
        position: 'right',
        formatter: '{b}',
		show: true
    },
    lineStyle: {
        color: 'source',
        {$curveness}
    },
    emphasis: {
        lineStyle: {
            width: 3
        }
    },
    draggable: false,
}

JS;
    }
    
    /**
     * build axes configuration
     *
     * @return string
     */
    protected function buildJsAxes() : string
    {
        if ($this->isPieChart() === true || $this->isGraphChart() === true) {
            return '';
        }
        $countAxisRight = 0;
        $countAxisLeft = 0;
        $widget = $this->getWidget();
        $xAxesJS = '';
        $yAxesJS = '';
        $zoom = '';
        $xZoomCount = 0;
        $yZoomCount = 0;
        foreach ($widget->getAxesX() as $axis) {
            $xAxesJS .= $this->buildJsAxisProperties($axis);
            if ($axis->isZoomable() === true) {
                $zoom .= $this->buildJsAxisZoom($axis, $xZoomCount);
                $xZoomCount++;
            }
        }
        foreach ($widget->getAxesY() as $axis) {
            if ($axis->isZoomable() === true) {
                $zoom .= $this->buildJsAxisZoom($axis, $yZoomCount);
                $yZoomCount++;
            }
            if ($axis->getPosition() === ChartAxis::POSITION_LEFT && $axis->isHidden() === false) {
                $countAxisLeft++;
                $yAxesJS .= $this->buildJsAxisProperties($axis, $countAxisLeft);
            } elseif ($axis->getPosition() === ChartAxis::POSITION_RIGHT && $axis->isHidden() === false) {
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
     * font family for axis labels
     * 
     * @return string
     */
    protected function baseAxisLabelFont() : string
    {
        return 'sans-serif';
    }
    
    /**
     * font size for axis labels
     * 
     * @return string
     */
    protected function baseAxisLabelFontSize() : string
    {
        return 12;
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
            $caption = $axis->getCaption();            
        } else {
            $caption = '';
        }
        
        if ($axis->hasGrid() === false) {
            $grid = 'false';
        } else {
            $grid = 'true';
        }
        if ($axis->getMinValue() === null) {
            $min = '';
        } else {
            $min = "min: '" . $axis->getMinValue() . "',";
        }
        if ($axis->getMaxValue() === null) {
            $max = '';
        } else {
            $max = "max: '" . $axis->getMaxValue() . "',";
        }
        $axisType = $axis->getAxisType();
        
        if ($axis->getDimension() == Chart::AXIS_X) {
            $nameLocation = "nameLocation: 'center',";
        } else {
            $nameLocation = '';
        }
        
        if ($axis->hasRotatedLabel() === true) {
            $rotate = 'rotate: 45,';
        } else {
            $rotate = '';
        }
        
        if ($axisType === ChartAxis::AXIS_TYPE_CATEGORY) {
            $interval = 'interval: 0';
            $axisTick = <<<JS
            
        axisTick: {
            alignWithLabel: true,
        },

JS;
        } else {
            $interval = '';
            $axisTick = '';
        }
        $maxInterval = '';
        /*if ($axisType === ChartAxis::AXIS_TYPE_TIME) {
            $maxInterval = 'minInterval: 3600 * 1000 * 24*30,';
        } else {
            $maxInterval = '';
        }*/
        $axisTypeLower = mb_strtolower($axisType);        
        $position = mb_strtolower($axis->getPosition());
        if ($axis->getDimension() == Chart::AXIS_Y) {
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
        if ($axis->getIndex() !== 0 && $axis->getDimension() == Chart::AXIS_X) {
            $onZero = 'axisLine: {onZero: false},';
        } else {
            $onZero = '';
        }
        
        return <<<JS
        
    {
        id: '{$axis->getIndex()}',
        name: '{$caption}',
        {$nameLocation}
        {$inverse}
        type: '{$axisTypeLower}',
        splitLine: { show: $grid },
        position: '{$position}',
        show: false,
        nameGap: {$nameGap},
        axisLabel: {
            fontFamily: '{$this->baseAxisLabelFont()}',
            fontSize: {$this->baseAxisLabelFontSize()},
            formatter: function(a) {
                return {$this->buildJsLabelFormatter($axis->getDataColumn(), 'a')}
            },
            {$rotate}
            {$interval}
        },
        axisPointer: {
            label: {
                formatter: function(params) {
                return {$this->buildJsLabelFormatter($axis->getDataColumn(), 'params.value')}
                },
            },
        },
        {$axisTick}
        {$onZero}
        {$min}
        {$max}
        {$maxInterval}
    },
    
JS;
    }
    
    /**
     * build axis zoom configuration
     *
     * @param ChartAxis $axis
     * @return string
     */
    protected function buildJsAxisZoom(ChartAxis $axis, $zoomCount = 0) : string
    {
        $offset = 5;
        $offset += $zoomCount * $this->baseZoomOffset();
        
        if ($this->getWidget()->getLegendPosition() === 'bottom' && $axis->getDimension() === Chart::AXIS_X) {
            $offset += 25;
        }
        if ($axis->getDimension() === Chart::AXIS_X) {
            $JsOffset = "bottom: {$offset},";
        } elseif ($axis->getDimension() === Chart::AXIS_Y) {
            $JsOffset = "right: {$offset},";
        } else {
            $JsOffset = '';
        }
        $filterMode = 'empty';
        if ($this->getWidget()->getSeries()[0] instanceof BarChartSeries) {
            if ($axis->getDimension() === Chart::AXIS_Y) {
                $filterMode = 'filter';
            }
        } else {
            if ($axis->getDimension() === Chart::AXIS_X) {
                $filterMode = 'filter';
            }
        }
        return <<<JS
        
    {
        type: 'slider',
        {$axis->getDimension()}AxisIndex: {$axis->getIndex()},
        filterMode: '{$filterMode}',
        labelFormatter: function(value, valueStr) {
            return {$this->buildJsLabelFormatter($axis->getDataColumn(), 'valueStr')}
        },
        //disables Zoom Label
        showDetail: false,
        {$JsOffset}
    },
    {
        type: 'inside',
        {$axis->getDimension()}AxisIndex: {$axis->getIndex()},
        filterMode: 'empty'
    },
    
JS;

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
        return 18;
    }
    
    /**
     * basic offset for legend
     *
     * @return int
     */
    protected function baseLegendOffset() : int
    {
        return 25;
    }
    
    /**
     * basic offset value that needs to be added for each zoom slider
     *
     * @return int
     */
    protected function baseZoomOffset() : int
    {
        return 40;
    }
    
    /**
     * javascript function body to draw chart, iniatlize global variables, show overlay message if data is empty
     *
     * @param string $dataJs
     * @return string
     */
    protected function buildJsRedrawFunctionBody(string $dataJs) : string
    {
        if ($this->getWidget()->getData()->hasUidColumn()) {
            $uidField =  $this->getWidget()->getData()->getUidColumn()->getDataColumnName();
        }
        if ($this->isPieChart() === true) {
            $js = $this->buildJsRedrawPie('newSelection');
        } elseif ($this->isGraphChart() === true) {
            $js = $this->buildJsRedrawGraph('newSelection');
        } else {
            $js = $this->buildJsRedrawXYChart('newSelection', 'seriesIndex');
        }       
        
        return <<<JS
        
    var rowData = $dataJs;
    var echart = {$this->buildJsEChartsVar()}
    var newSelection = undefined;
    var uidField = '{$uidField}' || undefined ;
    if (echart._oldSelection != undefined) {
        if (uidField != undefined) {
            newSelection = function (){
                for (var i = 0; i < rowData.length; i++) {
                    if (rowData[i][uidField] === echart._oldSelection[uidField]) {
                        return rowData[i];
                    }
                }
                return undefined
            }();
        } else {
            newSelection = function (){
                for (var i = 0; i < rowData.length; i++) {
                    if ({$this->buildJsRowCompare('rowData[i]', 'echart._oldSelection')}) {
                        return rowData[i];
                    }
                }
                return undefined
            }();
        }
    }
    var options = echart.getOption();
    var seriesIndex = undefined
    if (options != undefined) {
        for(var i = 0; i < options.series.length; i++) {
            if ('markLine' in options.series[i]) {
                if (options.series[i].markLine._show === true) {
                    seriesIndex = i;
                }
            }
        }
    }
    //reset Chart Configuration and variables bound to div before building new one
    {$this->buildJsDataResetter()}
    // if data is empty or not defined show overlay message
    if (! rowData || rowData.length === 0) {
        {$this->buildJsMessageOverlayShow($this->getWidget()->getEmptyText())}
        return;
    }
    echart.resize();
    echart._dataset = rowData;
    //hide overlay message
    {$this->buildJsMessageOverlayHide()}
    //build and set basic chart config and options 
    {$this->buildJsEChartsVar()}.setOption({$this->buildJsChartConfig()})
    //build and set dataset,config and options depending on chart type    
    $js
JS;
    }

    /**
     * javascript snippet to calculate offsets for axis and grid and draw Charts with X and Y axes
     * 
     * @return string
     */
    protected function buildJsRedrawXYChart(string $selection = 'undefined', string $series = 'undefined', string $dataJs = 'rowData') : string
    {
        $axesOffsetCalc = '';
        $axesJsObjectInit = '';
        //for each visible axis calculate necessary gap to next axis/chart borders
        //for X-Axis its based on the AxisIndex, for Y-Axis it's based on the length of the longest data value
        foreach ($this->getWidget()->getAxes() as $axis) {
            if ($axis->isHidden() === true) {
                continue;
            }
            
            $xAxisIndex = 0;
            if ($axis->getDimension() === Chart::AXIS_X) {
                $gap = ++$xAxisIndex . ' * 20 * 2 - 15';
                //for axes that have rotated label gap has to be calculated differently                
                if ($axis->hasRotatedLabel() === true) {
                    //rotation is 45 degress, therefore the gap should be the square root of
                    //2 times the square of the text length
                    $gap = 'canvasCtxt.measureText(val).width / Math.sqrt(2) + 15';
                }           
            } else {
                //$gap = 'len * (8 - Math.floor(len / 16))';
                $gap = 'canvasCtxt.measureText(val).width + 10';
            }
            $axesOffsetCalc .= <<<JS
            
        val = row['{$axis->getDataColumn()->getDataColumnName()}'];
        if (val === undefined || val === null) {
            len = 0;
        } else {
            val = {$this->buildJsLabelFormatter($axis->getDataColumn(), 'val')}
            len = (typeof val === 'string' || val instanceof String ? val.length : val.toString().length);
        }
        gap = {$gap};
        if (axes["{$axis->getDataColumn()->getDataColumnName()}"]['gap'] < gap) {
            axes["{$axis->getDataColumn()->getDataColumnName()}"]['gap'] = gap;
        }
        
JS;
            $postion = mb_strtolower($axis->getPosition());
            //if the axis has a caption the base gap is based on that length, else it's 0
            $baseGap = 0;
            if ($axis->getHideCaption() === false) {                
                if ($axis->getDimension() === Chart::AXIS_Y) {
                    $baseGap = strlen($axis->getCaption())*3.5;
                }                
                $caption = 'true';
            } else {                
                $caption = 'false';
            }
            if ($axis->hasRotatedLabel() === true) {
                $rotated = 'true';
            } else {
                $rotated = 'false';
            }
            //js snippet to build array containing every visible axis as object with its necessary gap
            //and other needed parameters
            $axesJsObjectInit .= <<<JS
            
    axes["{$axis->getDataColumn()->getDataColumnName()}"] = {
        caption: {$caption},
        category: "{$axis->getAxisType()}",
        gap: {$baseGap},
        dimension: "{$axis->getDimension()}",
        position: "{$postion}",
        index: "{$axis->getIndex()}",
        name: "{$axis->getDataColumn()->getDataColumnName()}",
        rotation : {$rotated},
    };
    
JS;
        }
        $widget = $this->getWidget();
        $zoomSet = 'no';
        if ($widget->getSeries()[0] instanceof BarChartSeries) {
            if ($widget->getAxesY()[0]->isZoomable() !== null) {
                $zoomSet = 'yes';
            }
        } else {
            if ($widget->getAxesX()[0]->isZoomable() !== null) {
                $zoomSet = 'yes';                
            }
        }
        
        return <<<JS

    
    
    // initalize axis array
    var axes = [];
    {$axesJsObjectInit}
    
    // Danach
    var val, gap;
    var len = 0;

    var canvasCtxt = $('#{$this->buildJsEChartsDivVar()} canvas').get(0).getContext('2d');
    var options = {$this->buildJsEChartsVar()}.getOption();
    var font = "{$this->baseAxisLabelFontSize()}" + "px " + "{$this->baseAxisLabelFont()}"
    canvasCtxt.font = font;


    // for each data row calculate the offset for the axis bound to a data value
    {$dataJs}.forEach(function(row){
        {$axesOffsetCalc}
    })
    
    var newOptions = {yAxis: [], xAxis: []};
    var axis;
    // offsets for the first axis at each position
    var offsets = {
        'top': 0,
        'right': 0,
        'bottom': 0,
        'left': 0
    };
    // for every visible axis, set the correct offset and that it is visible
    for (var i in axes) {
        axis = axes[i];
        if (axis.gap === 0 && {$dataJs}.length > 0) {   
            {$this->buildJsShowMessageError("'{$this->getWorkbench()->getCoreApp()->getTranslator()->translate('ERROR.ECHARTS.AXIS_NO_DATA')} \"' + axis.name + '\"'")}
        }
        //if the caption for axis is shown the gap for x Axes needs to be
        // set based on the axis.gap (means the space needed to show axis values)
        if (axis.rotation === true && axis.caption === true) { 
            var nameGap = axis.gap + {$this->baseAxisNameGap()};
            newOptions[axis.dimension + 'Axis'].push({
                offset: offsets[axis.position],
                nameGap: axis.gap,               
                show: true
            });
            offsets[axis.position] += nameGap;
        } else {
            newOptions[axis.dimension + 'Axis'].push({
                offset: offsets[axis.position],               
                show: true
            });
            if (axis.caption === true) {
                offsets[axis.position] += axis.gap + {$this->baseAxisNameGap()};
            } else {
                offsets[axis.position] += axis.gap;
            }
        }
        
        // increase the offset for the next axis at the same position by the gap calculated for this axis        
        /*if (nameGap === 0) {
            offsets[axis.position] += axis.gap;
        } else {
            offsets[axis.position] += nameGap;
        }

        offsets[axis.position] += axis.gap*/
        
        
    }
    
    // the grid margin at each side is the sum of each calculated axis gap for this side + the base margin
    var gridmargin = offsets;
    gridmargin['top'] += {$this->buildJsGridMarginTop()};
    gridmargin['right'] += {$this->buildJsGridMarginRight()};
    gridmargin['bottom'] += {$this->buildJsGridMarginBottom()};
    gridmargin['left'] += {$this->buildJsGridMarginLeft()};
    
    newOptions.grid = gridmargin;
    var oldOptions = {$this->buildJsEChartsVar()}.getOption()
    
    var zoomSet = "{$zoomSet}"
    if ({$dataJs}.length > 15 && zoomSet === 'no') {
        var oldOptions = {$this->buildJsEChartsVar()}.getOption()
        if (oldOptions.dataZoom.length === 0) {
            if (("_bar" in oldOptions.series[0]) === true) {
                var zoom = [{$this->buildJsAxisZoom($widget->getAxesY()[0])}]
                gridmargin['right'] += {$this->baseZoomOffset()}
            } else {
                var zoom = [{$this->buildJsAxisZoom($widget->getAxesX()[0])}]
                gridmargin['bottom'] += {$this->baseZoomOffset()}
            }
            newOptions.dataZoom = zoom            
        }
    }    
    {$this->buildJsEChartsVar()}.setOption(newOptions);
    
    var split = "{$this->getWidget()->getSeries()[0]->getSplitByAttributeAlias()}" || undefined
    if (split === undefined) {
        {$this->buildJsSplitCheck()}
    } 
    if (split === undefined) {
        {$this->buildJsEChartsVar()}.setOption({dataset: {source: {$dataJs}}})
    }
    else {
        {$this->buildJsSplitSeries()}
    }

    var selection = {$selection};
    if (selection != undefined) {
        if ({$series} != undefined) {
            var params = {seriesIndex: seriesIndex};
        } else {
            var params = {seriesIndex: 0};
        }
        params.data = selection;
        {$this->buildJsSingleClick('params')}
    }
    
    
JS;
    }
       
    /**
     * js snippet to check if data should be split
     * only supports single series
     * 
     * @return string
     */
    protected function buildJsSplitCheck(string $dataJs = 'rowData') : string
    {
        $widget = $this->getWidget();
        if (($widget->getSeries()[0]) instanceof BarChartSeries) {
            $axisKey = $widget->getAxesY()[0]->getDataColumn()->getDataColumnName();
        } else {
            $axisKey = $widget->getAxesX()[0]->getDataColumn()->getDataColumnName();
        }
        return <<<JS
    
    var keyValues = []
    var doubleValues = []
    //compare all X-Axes Key values in each row with each other
    for (var i = 0; i < {$dataJs}.length; i++) {
        var isDouble = false
        for (var j = 0; j < keyValues.length; j++) {
            if ({$dataJs}[i].{$axisKey} === keyValues[j]) {
                isDouble = true
                break
            }
        }
        // if value not yet appeared, push value into keyValues array
        if (isDouble === false) {
            var value = {$dataJs}[i].{$axisKey}
            keyValues.push(value)
        // if value already appeared
        } else {
            var alreadyinDouble = false
            // if it is already in doubleValues array and therefor not first double
            for (k = 0; k < doubleValues.length; k++) {
                if ({$dataJs}[i].{$axisKey} === doubleValues[k].{$axisKey}) {
                    alreadyinDouble = true
                    break
                }
            }
            // if value is first time a double push whole data row into doubleValues array
            if (alreadyinDouble === false) {
                var value = {$dataJs}[i]
                doubleValues.push(value)
            }
        }
    }
    var dataKeys = {$dataJs}.length === 0 ? [] : Object.keys({$dataJs}[0]);
    // for each object key in dataRow[0] check if value for that key in all objects in doubleValues array are equal
    // if all values for that key are equal, dataset will be split at that key 
    for (var j = 0; j < dataKeys.length; j++) {
        var valueMatch = false
        for (i = 1; i < doubleValues.length; i++) {
            if (doubleValues[i-1][dataKeys[j]] === doubleValues[i][dataKeys[j]]) {
                valueMatch = true
            } else {
                valueMatch = false
                break
            }
        }
        if (valueMatch === true) {
            split = dataKeys[j]
            break
        }
    }
    console.log('Split: ',split)

JS;
        
    }
    
    /**
    * js snippet to split the dataset and configure series for each dataset part
    *
    * @return string
    */
    protected function buildJsSplitSeries(string $dataJs = 'rowData') : string
    {
        return <<<JS
    
    var splitDatasetObject = {};
        for (var i=0; i < {$dataJs}.length; i++) {
        var p = {$dataJs}[i][split];
        if (!splitDatasetObject[p]) {
            splitDatasetObject[p] = [];
        }
        splitDatasetObject[p].push({$dataJs}[i]);
    }
    var splitDatasetArray = Object.keys(splitDatasetObject).map(i => splitDatasetObject[i]);
    var newNames = Object.keys(splitDatasetObject);
    var baseSeries = {$this->buildJsChartSeriesConfig($this->getWidget()->getSeries()[0])}
    var currentSeries = JSON.parse(JSON.stringify(baseSeries));
    
    currentSeries.name = newNames[0];
    currentSeries.datasetIndex = 0;
    var newSeriesArray = [currentSeries];

    for (var i = 1; i < newNames.length; i++) {
        currentSeries = JSON.parse(JSON.stringify(baseSeries));
        currentSeries.name = newNames[i];
        currentSeries.datasetIndex = i;
        currentSeries.markLine = baseSeries.markLine;
        newSeriesArray.push(currentSeries);
    }
    var dataset = [{source: splitDatasetArray[0]}]
    for (var i = 1; i < newNames.length; i++) {
        var set = {};
        set.source = splitDatasetArray[i];
        dataset.push(set);
    }
    var newOptions = {
        dataset: dataset,
        series: newSeriesArray }
    {$this->buildJsEChartsVar()}.setOption(newOptions)
    
JS;
    }
    
    /**
     * javascript snippet to transform data to match data required for pie charts and draw pie chart
     *
     * @return string
     */
    protected function buildJsRedrawPie(string $selection = 'undefined', string $dataJs = 'rowData') : string
    {
        return <<<JS
        
    var arrayLength = {$dataJs}.length;
    var chartData = [];
    for (var i = 0; i < arrayLength; i++) {
        var item = { value: {$dataJs}[i].{$this->getWidget()->getSeries()[0]->getValueDataColumn()->getDataColumnName()} , name: {$dataJs}[i].{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()} };
        chartData.push(item);
    }
    
    {$this->buildJsEChartsVar()}.setOption({
        series: [{
            data: chartData
        }],
        legend: {
            data: {$dataJs}.{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()}
        }
    })
    var selection = {$selection};
    if (selection != undefined) {
        var index = function(){
            for (var i = 0; i < chartData.length; i++) {
                if (chartData[i].name === selection.{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()}) {
                    return i
                }
            }                    
        }()
        var params = {seriesIndex: 0, dataIndex: index};
        params.data = {name: chartData[index].name};
        {$this->buildJsSingleClick('params')}
    }
    

JS;
        
    }
    
    /**
     * javascript snippet to transform data to match data required for graph charts and draw graph chart
     *
     * @return string
     */
    protected function buildJsRedrawGraph(string $selection = 'undefined', string $dataJs = 'rowData')
    {
        $series = $this->getWidget()->getSeries()[0];
        return <<<JS
        
    var nodes = [];
    var links = [];
    var node = {};
    var link = {};
    
    // for each data object add a node that's not already existing to the nodes array
    // and a link that's not already existing to the links array
    for (var i = 0; i < {$dataJs}.length; i++) {    	
		var existingNodeLeft = false;
        var existingNodeRight = false;
        for (var j = 0; j<nodes.length; j++) {
            // if the right object already exists at node, increase the symbol size of that node
			if (nodes[j].id === {$dataJs}[i].{$series->getRightObjectDataColumn()->getDataColumnName()}) {
				existingNodeRight = true;
                nodes[j].symbolSize += 1;
                nodes[j].value += 1;
			}
            // if the left object already exists at node, increase the symbol size of that node
			if (nodes[j].id === {$dataJs}[i].{$series->getLeftObjectDataColumn()->getDataColumnName()}) {
				existingNodeLeft = true;
                nodes[j].symbolSize += 1;
                nodes[j].value += 1;
			}
		}
        // if the left and right object are the same and not yet existing as node, only add the left object to the nodes
        if ({$dataJs}[i].{$series->getRightObjectDataColumn()->getDataColumnName()} === {$dataJs}[i].{$series->getLeftObjectDataColumn()->getDataColumnName()}) {
            existingNodeRight = true;
        }
        // if the left object is not existing as node yet, add it
		if (existingNodeLeft === false ) {
			node = {
				id: {$dataJs}[i].{$series->getLeftObjectDataColumn()->getDataColumnName()},
				name: {$dataJs}[i].{$series->getLeftObjectNameDataColumn()->getDataColumnName()},
                symbolSize: 10,
				value: 10,
			};
			nodes.push(node);
		}
        // if the right object is not existing as node yet, add it
		if (existingNodeRight === false ) {
			node = {
				id: {$dataJs}[i].{$series->getRightObjectDataColumn()->getDataColumnName()},
				name: {$dataJs}[i].{$series->getRightObjectNameDataColumn()->getDataColumnName()},
				symbolSize: 10,
				value: 10,
			};
		nodes.push(node);
		}
	
    	// if relation direction is "regular" left object is source node, right object is target node for that relation
        if ({$dataJs}[i].{$series->getDirectionDataColumn()->getDataColumnName()} == "regular") {
    		var source = {$dataJs}[i].{$series->getLeftObjectDataColumn()->getDataColumnName()};
    		var target = {$dataJs}[i].{$series->getRightObjectDataColumn()->getDataColumnName()};
    	// else right object is source and left object is target for that relation
        } else {
    		var source = {$dataJs}[i].{$series->getRightObjectDataColumn()->getDataColumnName()};
    		var target = {$dataJs}[i].{$series->getLeftObjectDataColumn()->getDataColumnName()};
    	}
        var existingLink = false;
        // for every relation check if it's not already existing in links array
        for (var j = 0; j<links.length; j++) {
            if (links[j].id === {$dataJs}[i].{$series->getRelationDataColumn()->getDataColumnName()}) {
                existingLink = true;
            }
        }
        // if relation is not existing yet as link, add it to links array
        if (existingLink === false) {
            link = {
        		id: {$dataJs}[i].{$series->getRelationDataColumn()->getDataColumnName()},
        		name: {$dataJs}[i].{$series->getRelationNameDataColumn()->getDataColumnName()},
        		source: source,
        		target: target,
        	};
        	links.push(link);
        }
    }
    var echart = {$this->buildJsEChartsVar()};
    echart.setOption({
    	series: [{
    		data: nodes,
            links: links,
    	}],
    });
    var selection = {$selection};
    if (selection != undefined) {
        var index = function(){
            for (var i = 0; i < nodes.length; i++) {
                if (nodes[i].id === selection.{$this->getWidget()->getSeries()[0]->getLeftObjectDataColumn()->getDataColumnName()}) {
                    return i
                }
            }                    
        }()
        var params = {seriesIndex: 0, dataIndex: index, dataType: 'node'};
        params.data = {id: nodes[index].id}; 
        {$this->buildJsSingleClick('params')}
        //echart._oldSelection = selection;
    }


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
        $countAxisLeft = 0;
        $countAxisRight = 0;
        $widget = $this->getWidget();
        $margin = 25;
        foreach ($this->getWidget()->getAxesY() as $axis) {
            if ($axis->getPosition() === ChartAxis::POSITION_LEFT && $axis->isHidden() === false && $axis->getHideCaption() === false ) {
                $countAxisLeft++;
            } elseif ($axis->getPosition() === ChartAxis::POSITION_RIGHT && $axis->isHidden() === false && $axis->getHideCaption() === false) {
                $countAxisRight++;
            }
        }
        if ($countAxisLeft > 0 || $countAxisRight > 0) {
            $margin = 15;
        }
        if ($countAxisLeft >= $countAxisRight) {
            $margin += $this->baseAxisNameGap() * $countAxisLeft;
        } else {
            $margin += $this->baseAxisNameGap() * $countAxisRight;
        }
        
        if ($this->legendHidden() === false && ($widget->getLegendPosition() === 'top' || $widget->getLegendPosition() === null)) {
            $margin += $this->baseLegendOffset();
        }
        return $margin;
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
        if ($rightAxis === true || $count != 0) {
            $margin = 0;
        } else {
            $margin = 40;
        }
        $margin += $this->baseZoomOffset() * $count;
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
        $margin = 0;
        $widget = $this->getWidget();
        foreach ($widget->getAxesX() as $axis) {
            if ($axis->isZoomable() === true) {
                $count++;
            }
        }
        if ($this->legendHidden() === false && $widget->getLegendPosition() === 'bottom') {
            $margin += $this->baseLegendOffset();
        }
        $margin += $this->baseZoomOffset() * $count;
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
     * function to check if graph is a graph series
     *
     * @return bool
     */
    protected function isPieChart() : bool
    {
        if ($this->getWidget()->getSeries()[0] instanceof PieChartSeries || $this->getWidget()->getSeries()[0] instanceof DonutChartSeries) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * function to check if chart is a graph chart
     *
     * @return bool
     */
    protected function isGraphChart() : bool
    {
        if ($this->getWidget()->getSeries()[0] instanceof GraphChartSeries) {
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
        if ($this->isPieChart() === true) {
            return <<<JS
            
{
	trigger: 'item',
	formatter: "{b} : {c} ({d}%)",
    confine: true,
},

JS;
        } elseif ($this->isGraphChart() === true) {
            return <<<JS

{
	formatter: function(params) {
		return params.data.name + " - " + params.data.id;
	},
    confine: true,
},

JS;
            
        } else {
            return <<<JS
            
{
	trigger: 'axis',
    confine: true,
    enterable: true,
    extraCssText: 'overflow-y: auto; max-height: 50%',
	axisPointer: {
		type: 'cross'
	},
    position: function (point) {
      //postion directly at cursor
      return [point[0]+5, point[1]+5];
    },
    formatter: function (params) {
        // params is ordered by value Axis (x Axis normally, y Axis for bar charts)
        var options = {$this->buildJsEChartsVar()}.getOption();                       
        // build table with header based on first value axis and it's label
        var stacked = true;
        for (i = 0; i < options.series.length; i++) {
            if (!("stack" in options.series[i])) {
                stacked = false;
                break;
            }
        }
        var tooltip = '<table class="exf-tooltip-table"><tr><th align = "left" colspan = "3">' + params[0].axisValueLabel + '</th></tr>';
        var tooltipPart = '';
        var currentAxis = params[0].axisIndex;
        // for each object in params build a table row
        params.forEach(({axisIndex, axisValueLabel, marker, value, seriesIndex, seriesName}) => {
            // get the correct formatter and the data for this object in params array
            if (("_bar" in options.series[seriesIndex]) == true) {
                var data = options.series[seriesIndex].encode.x;
                var Index = options.series[seriesIndex].xAxisIndex;
                var formatter = options.xAxis[Index].axisLabel.formatter;              
            } else {
                var data = options.series[seriesIndex].encode.y;
                var Index = options.series[seriesIndex].yAxisIndex;
                var formatter = options.yAxis[Index].axisLabel.formatter;                
            }
            var value = formatter(value[data]);
            // if this params object is bound to another axis as the ones before, build a new header with new label
            if (stacked === true) {
                if (axisIndex !== currentAxis) {
                    tooltip = tooltip + tooltipPart + '<tr><th colspan = "3">' + axisValueLabel + '</th></tr>';
                    currentAxis = axisIndex;
                }
                tooltipPart ='<tr><td>'+ marker + '</td><td>' + seriesName + '</td><td>'+ value + '</td></tr>' + tooltipPart;
            } else {
                if (axisIndex !== currentAxis) {
                    tooltipPart += '<tr><th align = "left" colspan = "3">' + axisValueLabel + '</th></tr>';
                    currentAxis = axisIndex;
                }
                tooltip += tooltipPart + '<tr><td>'+ marker + '</td><td>' + seriesName + '</td><td>'+ value + '</td></tr>';
                }
        });
        if (stacked === true) {
            tooltip += tooltipPart + '</tbody></table>';
        } else {
            tooltip += '</tbody></table>';
        }        
        return tooltip;
    },
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
        if ($this->isGraphChart() === true) {
            return '{show: false},';
        }
        $padding = '';
        $widget = $this->getWidget();
        $firstSeries = $widget->getSeries()[0];
        $position = $widget->getLegendPosition();
        if ($position === null && $firstSeries instanceof PieChartSeries) {
            $positionJs = "show: false";
        } elseif ($position == 'top' ) {
            $positionJs = "top: 'top',";
        } elseif ($position == 'bottom') {
            $positionJs = "top: 'bottom',";
        } elseif ($position == 'left') {
            $positionJs = "left: 'left', orient: 'vertical',";
        } elseif ($position == 'right') {
            $positionJs = "left: 'right', orient: 'vertical',";
        }
        if ($firstSeries instanceof PieChartSeries) {
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
     * build the id for the chart div element
     * 
     * @return string
     */
    protected function buildJsEChartsDivVar() : string
    {
        return "{$this->getId()}_echarts";
    }
    
    /**
     * build the chart element id
     *
     * @return string
     */
    protected function buildJsEChartsVar() : string
    {
        return "chart_{$this->buildJsEChartsDivVar()}";
    }
    
    /**
     * build echarts js function that is called when chart gets resized
     *
     * @return string
     */
    protected function buildJsEChartsResize() : string
    {
        return "{$this->buildJsEChartsVar()}.resize()";
    }
    
    /**
     * build echarts js function that shows loading symbol 
     *
     * @return string
     */
    protected function buildJsEChartsShowLoading() : string
    {
        return "{$this->buildJsEChartsVar()}.showLoading()";
    }
    
    /**
     * build echarts js function that hides loading symbol
     *
     * @return string
     */
    protected function buildJsEChartsHideLoading() : string
    {
        return "{$this->buildJsEChartsVar()}.hideLoading()";
    }
    
    /**
     * build function to get value of a selected data row
     *
     * @param unknown $column
     * @param unknown $row
     * @throws FacadeOutputError
     * @return string
     */
    public function buildJsValueGetter($column = null, $row = null) : string
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
                    if ({$this->buildJsEChartsVar()}._oldSelection != undefined) {
                        var selectedRow = {$this->buildJsEChartsVar()}._oldSelection;
                        if (selectedRow && '{$key}' in selectedRow) {
                            data = selectedRow["{$key}"];
                        }
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
    public function buildJsDataGetter(ActionInterface $action = null) : string
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
            $rows = "[{$this->buildJsEChartsVar()}._oldSelection]";
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
{$this->buildJsEChartsVar()}.clear();
var echart = {$this->buildJsEChartsVar()};
echart._dataset = undefined;
echart._oldSelection = undefined;
echart._doubleClkSelection = undefined;
echart._clickCount = 0;

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
        if ($widget->getLegendPosition() !== null) {
            return false;
        }
        $firstSeries = $widget->getSeries()[0];
        if (count($widget->getSeries()) == 1 && ($firstSeries instanceof PieChartSeries) === false) {
            if ($firstSeries->getValueDataColumn() === $firstSeries->getValueAxis()->getDataColumn()){
                return true;
            } else {
                return false;
            }
        }
        return false;
    }
    
}