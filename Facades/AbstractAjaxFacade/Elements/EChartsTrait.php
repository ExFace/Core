<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
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
use exface\Core\Widgets\Parts\Charts\Interfaces\iHaveVisualMapChartPart;
use exface\Core\Widgets\Parts\Charts\AreaChartSeries;
use exface\Core\Exceptions\Facades\FacadeOutputError;
use exface\Core\Widgets\Parts\Charts\GraphChartSeries;
use exface\Core\Widgets\DataButton;
use exface\Core\Widgets\Parts\Charts\HeatmapChartSeries;
use exface\Core\Widgets\Parts\Charts\VisualMapChartPart;
use exface\Core\Widgets\Parts\Charts\Interfaces\SplittableChartSeriesInterface;
use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Widgets\Parts\Charts\Interfaces\XYChartSeriesInterface;
use exface\Core\Widgets\Parts\Charts\SankeyChartSeries;
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\Core\DataTypes\NumberDataType;

/**
 * Trait to use for implementation of charts into a facade using echarts library.
 *
 * ## How to use
 *
 * 1. Add the following dependencies to the composer.json of the facade:
 *      ```
 *		"npm-asset/tinycolor2": "^1.4.2",
 *		"npm-asset/tinygradient": "^1.1.4"
 *      ```
 * 2. Add the following lines to the config of the facade:
 *      ```
 *      "LIBS.ECHARTS.ECHARTS_JS": "exface/Core/Facades/AbstractAjaxFacade/js/echarts/echarts.custom.min.js",
 *      "LIBS.TINYCOLOR.JS": "npm-asset/tinycolor2/dist/tinycolor-min.js",
 *      "LIBS.TINYGRADIENT.JS": "npm-asset/tinygradient/browser.js",
 *      "WIDGET.CHART.COLORS": ["#c23531", "#2f4554", "#61a0a8", "#d48265", "#91c7ae", "#749f83", "#ca8622", "#bda29a",
 * "#6e7074", "#546570", "#c4ccd3"],
 *      ```
 * 3. Use the trait in a facade element - see examples in \exface\JEasyUIFacade\Facades\Elements\euiChart.php
 * or \exface\UI5Facade\Facades\Elements\UI5Chart.php.
 * 4. It is recommended to add eCharts as a composer dependency to make it appear in the list of
 * installed packages and licenses. Add `"npm-asset/echarts" : "^5"` to the `require` section of
 * the facade's `composer.json`.
 *
 * To use the EChartsTrait in a facade add in the function where the HTML for the site is created
 * the following function `addChartButtons()` to add the buttons to change the chart type to your
 * site. Also you should add a resize script which, at one point, calls the `buildJsEChartsResize()`
 * function from the trait. Generating the javascript for the site call the following functions from
 * the trait:
 *
 * - `buildJsEChartsVar()` -> generate a js variable the echarts component will be accessable on
 * - `buildJsFunctions()` -> to build and add all the javascript function needed for echarts to work
 * correctly
 * - `buildJsEChartsInit()` -> initialize the echarts component (possible custom implementation is
 * needed for the facade)
 * - `buildJsRefresh()` -> add the function to refresh the chart
 *
 * Its also necessary to implement the function buildJsDataLoadFunctionBody which should provide a
 * javascript function the provides/loads the data for the chart.
 *
 * Its recommended to implement a function like `buildJsDataLoaderOnLoaded()` which gets called after
 * data fetching from a server or such was succesful. This function should call the EChartsTrait function
 * `buildJsRedraw()`, with the data rows als parameter, to redraw the chart with the new data.
 *
 * The trait also provides the functions `buildJsEChartsShowLoading()` and `buildJsEChartsHideLoading()`
 * which might be called when the site is busy loading data, and when its finished loading data.
 *
 * For an example of how to use the ECahrtsTrait in a facade, see the file
 * `exface\JEasyUIFacade\Facades\Elements\EuiChart.php` which shows the implamantation for the JeasyUI Facade.
 * 
 * ## Theming
 * 
 * The colors of chart series alternate automatically. A list of available colors is defined in the
 * configuration option `WIDGET.CHART.COLORS` in each facade. Additionally a `theme.js` file can
 * be used as described in the docs of eCharts - see the UI5 implementation UI5Chart.php for an
 * example.
 *
 * ## Updating the custom ECharts build
 *
 * A custom build echarts javascript file is used. The echarts website provides a
 * tool to build a custom version of their library: https://echarts.apache.org/en/builder.html
 * It is possible that the tool does not work correctly with the Google Chrome browser
 * (it stops during the .js file creation), if that happens use Firefox to create the custom
 * .js file. The current custom file includes the following chart types, coordinate systems,
 * components and other parts:
 *
 * - Charts: Bar, Line, Pie, Scatter, Heatmap, Sunburst, Graph, Gauge
 * - Coordinate Systems: Grid, Polar, SingleAxis
 * - Components: Title, Legend, Tooltip, MarkPoint, MarkLine, MarkArea, DataZoom, VisualMap
 * - Others: Utilities, CodeCompression
 *
 * @link https://www.echartsjs.com/en/index.html
 *
 * @method Chart getWidget()
 * @author Ralf Mulansky
 *
 */
trait EChartsTrait
{
    use JsValueScaleTrait;
    
    private $chartTypeButtonGroup = null;
    
    //this should be constants but traits do not support constants
    private $chartTypes = [
        "CHART_TYPE_PIE" => 'pie',        
        "CHART_TYPE_XY" => 'xy_chart',        
        "CHART_TYPE_GRAPH" => 'graph',        
        "CHART_TYPE_HEATMAP" => 'heatmap',        
        "CHART_TYPE_SANKEY" => 'sankey'
    ];

    /**
     * @return string
     */
    protected function getInvokeLegendActiveGetter() : string
    {
        return 'invokeLegendActiveGetter';
    }

    /**
     * @return string
     */
    protected function getInvokeLegendDisabledGetter() : string
    {
        return 'invokeLegendDisabledGetter';
    }
    
    /**
     * @return string
     */
    protected function getLegendActiveToken() : string
    {
        return '~legend_active';
    }

    /**
     * @return string
     */
    protected function getLegendDisabledToken() : string
    {
        return '~legend_disabled';
    }
    
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
     * Function to build the div element for the chart
     *
     * @param string $style
     * @return string
     */
    protected function buildHtmlChart($style = 'height:100%; min-height: 100px; overflow: hidden;') : string
    {
        $hint = $this->buildHintText($this->getWidget()->getHint());
        if ($hint) {
            $hint = $this->escapeString($hint, true, false);
            $hint = str_replace("\\n", "\n", $hint);
        } else {
            $hint = '""';
        }
        return '<div id="' . $this->getId() . '" class="exf-chart" style="' . $style . '" title=' . $hint . '></div>';
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
        if ($this->getChartType() === $this->chartTypes['CHART_TYPE_HEATMAP']) {
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.ECHARTS.ECHARTSHEATMAP_JS') . '"></script>';
        } else {
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.ECHARTS.ECHARTS_JS') . '"></script>';
        }
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.TINYCOLOR.JS') . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.TINYGRADIENT.JS') . '"></script>';
        
        foreach ($this->getWidget()->getData()->getColumns() as $col) {
            $formatter = $this->getFacade()->getDataTypeFormatter($col->getDataType());
            $includes = array_merge($includes, $formatter->buildHtmlBodyIncludes($this->getFacade()));
        }
        
        return $includes;
    }
    
    /**
     * Configurates the specific chart buttons and adds them to the toolbar parts
     * 
     */
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
        if ($this->getChartType() === $this->chartTypes['CHART_TYPE_GRAPH']) {
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
        
        $exportPNGUxon = $buttonTemplate->copy();        
        $exportPNGUxon->setProperty('caption', "{$this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.EXPORTPNG.NAME')}");
        $exportPNGUxon->setProperty('icon', 'file-image-o');
        $exportPNGUxon->setProperty('hide_caption', false);
        $exportPNGUxon->setProperty('visibility', 'optional');
        $exportPNGBtn = WidgetFactory::createFromUxon($widget->getPage(), $exportPNGUxon, $menu);
        $exportPNGBtn->getAction()->setScript($this->buildJsExport('png'));
        $tb->getButtonGroupForGlobalActions()->addButton($exportPNGBtn);
        
        $exportJPGUxon = $exportPNGUxon->copy();
        $exportJPGUxon->setProperty('caption', "{$this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.EXPORTJPEG.NAME')}");
        $exportJPGBtn = WidgetFactory::createFromUxon($widget->getPage(), $exportJPGUxon, $menu);
        $exportJPGBtn->getAction()->setScript($this->buildJsExport('jpeg'));
        $tb->getButtonGroupForGlobalActions()->addButton($exportJPGBtn);
        
        return;
    }
    
    /**
     * Returns the javascript to export the Chart in the specified filetype, supported right now are ´jpeg´ and ´png´
     * 
     * @param string $filetype
     * @return string
     */
    protected function buildJsExport(string $filetype) {      
        return <<<JS
(function() {
    var downloadImgSrc = {$this->buildJsEChartsVar()}.getDataURL({
        type: "{$filetype}",
        pixelRatio: 4,
        backgroundColor: '#fff'
    });
    const link = document.createElement("a");
    link.href = downloadImgSrc;
    link.download = "{$this->getWidget()->getCaption()}.{$filetype}";
    link.click();
}())

JS;
        
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
        if ($this->getChartType() === $this->chartTypes['CHART_TYPE_GRAPH']) {
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
                /*lineStyle: {
                    curveness: 0.2
                },*/
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
            {$this->buildJsRefresh()}
            
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
    protected function buildJsEChartsRefresh()
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
            // check if _redrawSelection is not undefined, means the select is called for a redraw with a row selected before the redraw
            if (echart._redrawSelection !== undefined) {
                //if the selected row before the redraw is in new dataset and got selected again, dont call onChangeScripts
                if ({$selection} !== undefined && {$this->buildJsRowCompare('echart._redrawSelection', $selection)}) {
                    echart._oldSelection = {$selection};
                    echart._redrawSelection = undefined;
                    return;
                }
                echart._oldSelection = {$selection};
                {$this->getOnChangeScript()}
                echart._redrawSelection = undefined;
                return;
            }
            if (echart._oldSelection === undefined && {$selection} === undefined) {
                return;
            }
            if (echart._oldSelection === undefined) {
                echart._oldSelection = {$selection};
            } else {
                if (({$this->buildJsRowCompare('echart._oldSelection', $selection)}) === false) {
                    echart._oldSelection = {$selection};
                } else {
                    return;
                }
            }
            {$this->getOnChangeScript()}
            return;
            
JS;
    }
    
    
    /**
     * returns the data row from the initial dataset for a selection on a chart
     * TODO implementation for sankey chart is missing
     *
     * @param string $selection
     * @return string
     */
    protected function buildJsGetSelectedRowFunction(string $selection) : string
    {
        switch ($this->getChartType()) {
            case $this->chartTypes['CHART_TYPE_PIE']:
                return <<<JS
                
                    function(){
                        var dataset = {$this->buildJsEChartsVar()}._dataset;
                        var selectedRow = {$selection};
                        for (var i = 0; i < dataset.length; i++) {
                            if (dataset[i].{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()} == selectedRow._key) {
                                return dataset[i];
                            }
                        }
                        return '';
                    }()
                    
JS;
            case $this->chartTypes['CHART_TYPE_GRAPH']:
                return <<<JS
                
                    function(){
                        var dataset = {$this->buildJsEChartsVar()}._dataset;
                        var selection = {$selection};
                        // searches first if a left object UID matches the data.id
                        for (var i = 0; i < dataset.length; i++) {
                            if (dataset[i].{$this->getWidget()->getSeries()[0]->getLeftObjectDataColumn()->getDataColumnName()} == selection.id) {
                                return dataset[i];
                            }
                        }/*
                        // if no node matches, then searches if a relation UID matches the data.id
                        for (var i = 0; i < dataset.length; i++) {
                            if (dataset[i].{$this->getWidget()->getSeries()[0]->getRelationDataColumn()->getDataColumnName()} == selection.id) {
                                return dataset[i];
                            }
                        }*/
                        return '';
                    }()
                    
JS;
            case $this->chartTypes['CHART_TYPE_SANKEY']:
                return <<<JS
                    ''

JS;
            default:
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
            var oldSelection = {$this->buildJsEChartsVar()}._oldSelection;
            if (clickCount === 1) {
                {$this->buildJsEChartsVar()}._doubleClkSelection = selected;
                {$this->buildJsSingleClick($params)}
                setTimeout(function(){
                    clickCount = 0;
                    {$this->buildJsEChartsVar()}._clickCount = clickCount;
                    {$this->buildJsEChartsVar()}._doubleClkSelection = undefined;
                }, 500);
            } else {
                if ({$this->buildJsEChartsVar()}._doubleClkSelection != undefined && {$this->buildJsEChartsVar()}._doubleClkSelection == selected) {
                    // do nothing
                } else {
                    {$this->buildJsEChartsVar()}._doubleClkSelection = selected;
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
                }();
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
        $invokeActiveGetter = substr_replace($this->getController()->buildJsEventHandler($this, $this->getInvokeLegendActiveGetter(), false), 'aLegendActive', -1, 0);
        $invokeDisabledGetter = substr_replace($this->getController()->buildJsEventHandler($this, $this->getInvokeLegendDisabledGetter(), false), 'aLegendDisabled', -1, 0);
        
        return <<<JS
        
        {$this->buildJsEChartsVar()}.on('legendselectchanged', function(params){
            // Invoke getters.
            var aLegendActive = Object.keys(params.selected).filter(function (item) {return params.selected[item];});
            {$invokeActiveGetter};
            
            var aLegendDisabled = Object.keys(params.selected).filter(function (item) {return !params.selected[item];});
            {$invokeDisabledGetter};
            
            //Check if series gets hidden, if not (means getting shown) do nothing
            var options = {$this->buildJsEChartsVar()}.getOption();
            if (params.selected[params.name] === false) {
                if (options.series[0].seriesType === 'pie') {
                    //do nothing
                } else {
                    var newOptions = {series: []};
                    var markLineSet = false;
                    var markLineData;
                    var axisIndex;
                    options.series.forEach(function(series){
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
                    options.series.forEach(function(series){
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
     * build function to get value of a selected data row
     *
     * @param string $column
     * @param int $row
     * @throws FacadeOutputError
     * @return string
     */
    public function buildJsValueGetter($column = null, $row = null) : string
    {
        if($column === $this->getLegendActiveToken()|| $column == $this->getLegendDisabledToken()) {
            $column = str_replace('~', '', $column);
            
            return <<<JS
            
            (function ({$column}){
                return {$column}.join(', ');
            })(oEvent)
JS;

        } else if ($column != null) {
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
        
                (function(){
                    var data = '';
                    var oChart = {$this->buildJsEChartsVar()};
                    if (oChart === undefined) {
                        return '';
                    }
                    try {
                        var oldSelection = {$this->buildJsEChartsVar()}._oldSelection;
                    } catch (e) {
                        console.warn('Cannot get value of chart:', e);
                        return '';
                    }
                    if (oldSelection != undefined) {
                        var selectedRow = oldSelection;
                        if (selectedRow && '{$key}' in selectedRow) {
                            data = selectedRow["{$key}"];
                        }
                    }
                return data;
                })()
                
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
        switch ($this->getChartType()) {
            case $this->chartTypes['CHART_TYPE_PIE']:
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
                name = {$this->buildJsLabelFormatter($this->getWidget()->getSeries()[0]->getTextDataColumn(), "name")}
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
            
            case $this->chartTypes['CHART_TYPE_GRAPH']:
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
                
            case $this->chartTypes['CHART_TYPE_HEATMAP']:
                return '';
            case $this->chartTypes['CHART_TYPE_SANKEY']:
                return '';
            default:
                return <<<JS

        var echart = {$this->buildJsEChartsVar()};
        var params = {$params};
        var dataRow = {$this->buildJsGetSelectedRowFunction('params.data')};
        var options = echart.getOption();
        var newOptions = {series: []};
        options.series.forEach(function(series){
            newOptions.series.push({markLine: {data: {}, _show: false}});
        });
        // if the chart is a barchart
        if (("_bar" in options.series[params.seriesIndex]) == true) {
            var value = dataRow[options.series[params.seriesIndex].encode.y];
            if (value === null) {
                value = "null";
            }
            newOptions.series[params.seriesIndex].markLine.data = [
                {
    				yAxis: value
    			}
            ];
            newOptions.series[params.seriesIndex].markLine._show = true;
        // if the chart is not a barchart
        } else {
            var value = dataRow[options.series[params.seriesIndex].encode.x];
            if (value === null) {
                value = "null";
            }
            newOptions.series[params.seriesIndex].markLine.data = [
                {
    				xAxis: value
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
                options.series.forEach(function(series){
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
        
        if ($this->getChartType() === $this->chartTypes['CHART_TYPE_GRAPH']) {
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
        $visualMapConfig = '';
        $visualMapCount = 0;
        foreach ($series as $s) {
            if ($s instanceof PieChartSeries && count($series) > 1) {
                throw new FacadeUnsupportedWidgetPropertyWarning('The facade "' . $this->getFacade()->getAlias() . '" does not support pie charts with multiple series!');
            }
            if ($s instanceof GraphChartSeries && count($series) > 1) {
                throw new FacadeUnsupportedWidgetPropertyWarning('The facade "' . $this->getFacade()->getAlias() . '" does not support graph charts with multiple series!');
            }
            if ($s instanceof SankeyChartSeries && count($series) > 1) {
                throw new FacadeUnsupportedWidgetPropertyWarning('The facade "' . $this->getFacade()->getAlias() . '" does not support sankey charts with multiple series!');
            }
            if ($s instanceof HeatmapChartSeries && count($series) > 1) {
                throw new FacadeUnsupportedWidgetPropertyWarning('The facade "' . $this->getFacade()->getAlias() . '" does not support heatmap charts with multiple series!');
            }
            if ($s instanceof SplittableChartSeriesInterface && $s->isSplitByAttribute() && ! $this->canSplitSeries($s)) {
                throw new FacadeUnsupportedWidgetPropertyWarning('The facade "' . $this->getFacade()->getAlias() . '" does not support split by attribute with multiple series!');
            }
            
            $seriesConfig .= $this->buildJsChartSeriesConfig($s) . ',';
            if ($s instanceof iHaveVisualMapChartPart && $s->hasVisualMap() === true) {
                $visualMapConfig .= $this->buildJsVisualMapConfig($s, $visualMapCount) . ',';
                $visualMapCount++;
            }
            if ($s instanceof BarChartSeries && count($series) > 1) {
                foreach ($series as $sOther) {
                    if ($sOther instanceof BarChartSeries === false) {
                        throw new FacadeUnsupportedWidgetPropertyWarning('The facade "' . $this->getFacade()->getAlias() . '" does not support bar charts mixed with other chart types!');
                    }
                }
            }
        }
        $visualMapJs = '';
        if ($visualMapConfig !== '') {
            $visualMapJs = "visualMap: [{$visualMapConfig}],";
        }
        $colorScheme = $this->getWidget()->getColorScheme() ?? 'null';
        $colors = $this->getColorSchemeColors();
        $colorsJs = ! empty($colors) ? json_encode($colors) : 'null';
        return <<<JS
        
{

    tooltip : {$this->buildJsChartPropertyTooltip()}
   	legend: {$this->buildJsChartPropertyLegend()}
	series: [{$seriesConfig}],
    {$visualMapJs}
    {$this->buildJsAxes()}
    color: function() {
        // rotate colors for every chart widget to make them all look different
        var iCnt = 0;
        var oChart = {$this->buildJsEChartsVar()};
        var oOpts = oChart.getOption() || {};
        var aColors = ({$colorsJs} || oOpts.color) || [];
        var jqCharts = $('.exf-chart');
        var iColorScheme = $colorScheme;
        
        if (iColorScheme !== null) {
            iCnt = iColorScheme;
        } else {
            for (var i = 0; i < jqCharts.length; i++) {
                if (jqCharts[i] !== oChart.getDom()) {
                    iCnt++;
                } else {
                    break;
                }
            }
        }
        
        for (var i = 0; i < iCnt; i++) {
            aColors.push(aColors.shift());
        }
        return aColors;
    }()
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
            case $series instanceof HeatmapChartSeries:
                return $this->buildJsHeatmapChart($series);
            case $series instanceof SankeyChartSeries:
                return $this->buildJsSankeyChart($series);
        }
        
        return '';
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
        //TODO option to show label, define position of it, maybe rotation etc.
        $label = '';
        if ($series->getShowValues() === true) {
            $label = <<<JS

    label: {
        show: true,
        formatter: function(params) {
            return {$this->buildJsLabelFormatter($series->getValueDataColumn(), 'params.value.' . $series->getValueDataColumn()->getDataColumnName())}
        }
    },
         
JS;
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
    {$label}
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
        $itemStyleJs = '';
        if ($series->getTextDataColumn()->getCellWidget() instanceof iHaveColorScale) {
            $semanticColors = $this->getFacade()->getSemanticColors();
            $semanticColorsJs = json_encode(empty($semanticColors) ? new \stdClass() : $semanticColors);
            $itemStyleJs = <<<JS

    itemStyle: {
        color: function(params) {
            var oSemanticColors = $semanticColorsJs;
            var sValue = params.data._key;
            var sColor = {$this->buildJsScaleResolver('sValue', $series->getTextDataColumn()->getCellWidget()->getColorScale(), $series->getTextDataColumn()->getCellWidget()->isColorScaleRangeBased())};
            if (sColor.startsWith('~')) {
                sColor = oSemanticColors[sColor] || '';
            } 
            if (sColor !== '' && sColor !== undefined && sColor !== 'undefined') {
                return sColor;
            }
            var oOptions = {$this->buildJsEChartsVar()}.getOption();
            var aColors = oOptions.color;
            var iColorsCount = aColors.length;
            var iIndex = params.dataIndex;
            while (iIndex >= iColorsCount) {
                iIndex = iIndex - iColorsCount;
            }
            return aColors[iIndex];
        }
    },
            
JS;        
        }
        $radius = $series->getInnerRadius();
        
        return <<<JS
        
{
    type: 'pie',
    radius: ['$radius','60%'],
    center: ['$centerX', '50%'],
    data: [],
    label: {$label},
    {$itemStyleJs}
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
    //autoCurveness: 20,
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
        scale: true,
        focus: 'adjacency',
        lineStyle: {
            width: 3
        }
    },
    draggable: false,
}

JS;
    }
    
    /**
     * build heatmap series configuration
     *
     * @param HeatmapChartSeries $series
     * @return string
     */
    protected function buildJsHeatmapChart(HeatmapChartSeries $series) : string
    {
        $show = "show: true,";
        if ($series->getShowValues() === false) {
            $show = "show: false,";
        }
        
        $borders = '';
        if ($series->getShowBorders()) {
            $borders = <<<JS
                borderWidth: 1,
                borderColor: 'rgba(255, 255, 255, 1)'

JS;
        }
        
        return  <<<JS
        
        {
            name: '{$series->getCaption()}',
            _index: {$series->getIndex()},
            type: 'heatmap',
            label: {
                normal: {
                    {$show}
                    formatter: function(param){
                        if (param.data['{$series->getValueDataColumn()->getDataColumnName()}'] ===  0) {
                            return 'N/A';
                        } else {
                            return param.data['{$series->getValueDataColumn()->getDataColumnName()}']
                        }
                    }
                }
            },
            emphasis: {
                itemStyle: {
                    shadowBlur: 10,
                    shadowColor: 'rgba(0, 0, 0, 0.5)'
                }
            },
            itemStyle: {
                {$borders}
            },
            coordinateSystem: 'cartesian2d',
            encode: {
                x: '{$series->getXAxis()->getDataColumn()->getDataColumnName()}',
                y: '{$series->getYAxis()->getDataColumn()->getDataColumnName()}'
            },
            xAxisIndex: {$series->getXAxis()->getIndex()},
            yAxisIndex: {$series->getYAxis()->getIndex()},
        }
        
JS;
    }
    
    /**
     *
     * @param SankeyChartSeries $series
     * @return string
     */
    protected function buildJsSankeyChart(SankeyChartSeries $series) : string
    {
        return <<<JS
        
        {
            type: 'sankey',
            focusNodeAdjacency: 'allEdges',
            itemStyle: {
                borderWidth: 1,
                borderColor: '#aaa'
            },
            lineStyle: {
                color: 'source',
                curveness: 0.5
            },
            data: [],
            links: []
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
        if ($this->getChartType() !== $this->chartTypes['CHART_TYPE_XY'] && $this->getChartType() !== $this->chartTypes['CHART_TYPE_HEATMAP']) {
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
            $xAxesJS .= $this->buildJsAxisProperties($axis, 1);
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
            if ($axis->getPosition() === ChartAxis::POSITION_LEFT) {
                //only if the axis is shown the count to calculate the name gap need to be increased
                if ($axis->isHidden() === false) {
                    $countAxisLeft++;
                }
                $yAxesJS .= $this->buildJsAxisProperties($axis, $countAxisLeft);
            } elseif ($axis->getPosition() === ChartAxis::POSITION_RIGHT) {
                //only if the axis is shown the count to calculate the name gap need to be increased
                if ($axis->isHidden() === false) {
                    $countAxisRight++;
                }
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
        
        $axisType = $axis->getAxisType();
        if (! $axis->getHideCaption()) {
            $caption = $axis->getCaption();
        } else {
            $caption = '';
        }
        
        if (! $axis->isHidden()) {
            $axisPointer = <<<JS

        axisPointer: {
            label: {
                formatter: function(params) {
                    return {$this->buildJsLabelFormatter($axis->getDataColumn(), 'params.value')}
                },
            },
        },

JS;
        } else {
            $axisPointer = '';
        }
        
        if ($axis->hasGrid() === false) {
            $grid = 'false';
        } else {
            $grid = 'true';
        }
        if ($axis->hasGridArea() === false) {
            $gridArea = 'false';
        } else {
            $gridArea = 'true';
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
        if ($axis->getDimension() == Chart::AXIS_X) {
            $nameLocation = "nameLocation: 'center',";
        } else {
            $nameLocation = '';
        }
        
        if ($axis->hasRotatedLabel() === true) {
            $rotateValue = $axis->getRotateLabelsDegree();
            $rotate = "rotate: {$rotateValue},";
        } else {
            $rotate = '';
        }
        
        if ($axis->hasTicksForEveryValue()) {
            $interval = 'interval: 0';
            $axisTick = <<<JS
            
        axisTick: {
            alignWithLabel: false,
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
        
        $isNumericAxisJs = $axis->getDataColumn()->getDataType() instanceof NumberDataType ? 'true' : 'false';
        
        //initially hide all axes, so they are only shown after calculation for the gaps and everything is done
        return <<<JS
        
    {
        id: '{$axis->getIndex()}',
        show: false,
        name: '{$caption}',
        {$nameLocation}
        {$inverse}
        type: '{$axisTypeLower}',
        splitLine: {
            show: $grid
        },
        splitArea: {
            show: $gridArea
        },
        position: '{$position}',
        nameGap: {$nameGap},
        axisLabel: {
            fontFamily: '{$this->baseAxisLabelFont()}',
            fontSize: {$this->baseAxisLabelFontSize()},
            formatter: function(a) {
                var bIsNumber = $isNumericAxisJs;
                if (bIsNumber && Number.isInteger(a)){
                    return a;
                }
                return {$this->buildJsLabelFormatter($axis->getDataColumn(), 'a')}
            },
            {$rotate}
            {$interval}
        },
        {$axisPointer}
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
            $hasVisualMap = false;
            foreach ($this->getWidget()->getSeries() as $s) {
                if ($s instanceof iHaveVisualMapChartPart && $s->hasVisualMap() === true && $s->getVisualMap()->getShowScaleFilter() === true) {
                    $hasVisualMap = true;
                    break;
                }
            }
            if ($hasVisualMap === true) {
                $offset += $this->baseZoomOffset();
            }
            $JsOffset = "bottom: {$offset},";
        } elseif ($axis->getDimension() === Chart::AXIS_Y) {
            $offset += 25;
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
     * build the configuration for the VisualMap part graph (for now only used in heatmap graphs)
     *
     * @param ChartSeries $series
     * @return string
     */
    protected function buildJsVisualMapConfig(iHaveVisualMapChartPart $series, int $count = null) : string
    {
        $visualMap = $series->getVisualMap();
        if ($visualMap === null) {
            return '';
        }
        
        $type = '';
        $splitNumber = '';
        $dragable = '';
        if ($visualMap->getUseColorGroups() === null) {
            $type = VisualMapChartPart::VISUAL_MAP_TYPE_CONTINUOUS;
            $dragable = "calculable: true,";
        } else {
            $type = VisualMapChartPart::VISUAL_MAP_TYPE_PIECEWISE;
            $splitNumber = 'splitNumber: ' . $visualMap->getUseColorGroups() . ',';
        }
        $type = strtolower($type);
        $show = 'true';
        if ($visualMap->getShowScaleFilter() === false) {
            $show = 'false';
        }
        $inRange = '';
        if (count($visualMap->getColors()) > 0) {
            $colors = json_encode($visualMap->getColors());
            $inRange = "inRange: {color: {$colors}},";
        }
        if ($count === 0) {
            $left = "'center'";
        } else {
            $left = $this->buildJsGridMarginLeft() + $count * $this->baseVisualMapOffset();
        }
        
        return <<<JS
        
        {
            type: '{$type}',
            dimension: '{$series->getValueDataColumn()->getDataColumnName()}',
            min: {$visualMap->getMin()},
            max: {$visualMap->getMax()},
            show: {$show},
            maxOpen: true,
            minOpen: false,
            {$dragable}
            {$splitNumber}
            {$inRange}
            formatter: function(a,b) {
                if (b === undefined) {
                    return {$this->buildJsLabelFormatter($series->getValueDataColumn(), 'a')};
                }
                var nr1 = {$this->buildJsLabelFormatter($series->getValueDataColumn(), 'a')};
                var nr2 = {$this->buildJsLabelFormatter($series->getValueDataColumn(), 'b')};
                return '> ' + nr1 + ' - ' + nr2;
            },
            orient: 'horizontal',
            left: $left,
            top: 'bottom'
        }
        
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
     * basic offset value that needs to be added to the visualMap `left` property for each additional visualMap
     *
     * @return int
     */
    protected function baseVisualMapOffset() : int
    {
        return 250;
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
        switch ($this->getChartType()) {
            case $this->chartTypes['CHART_TYPE_PIE']:
                $js = $this->buildJsRedrawPie('newSelection', 'rowData');
                break;
            case $this->chartTypes['CHART_TYPE_GRAPH']:
                $js = $this->buildJsRedrawGraph('newSelection', 'rowData');
                break;
            case $this->chartTypes['CHART_TYPE_SANKEY']:
                $js = $this->buildJsRedrawSankey('newSelection', 'rowData');
                break;
            default:
                $js = $this->buildJsRedrawXYChart('newSelection', 'seriesIndex', 'rowData');
        }
        /*if ($this->isPieChart() === true) {
         $js = $this->buildJsRedrawPie('newSelection');
         } elseif ($this->isGraphChart() === true) {
         $js = $this->buildJsRedrawGraph('newSelection');
         } else {
         $js = $this->buildJsRedrawXYChart('newSelection', 'seriesIndex');
         }*/
        
        return <<<JS
        
    var rowData = $dataJs;
    var echart = {$this->buildJsEChartsVar()}
    var newSelection = undefined;
    var uidField = '{$uidField}' || undefined;
    //save the old selection to check later if after redraw it is still selected and therefor no onChangeScripts need to be called
    echart._redrawSelection = echart._oldSelection;   
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
    // save the row that was selected before redraw, need later to check that its a redraw and selection didnt change (or changed)
    echart._prevRedrawSelection = undefined;
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
    protected function buildJsRedrawXYChart(string $selectionJs = 'undefined', string $seriesIndexMarkedJs = 'undefined', string $dataJs = 'rowData') : string
    {
        $axesOffsetCalc = '';
        $axesJsObjectInit = '';
        //for each visible axis calculate necessary gap to next axis/chart borders
        //for X-Axis its based on the AxisIndex, for Y-Axis it's based on the length of the longest data value
        foreach ($this->getWidget()->getAxes() as $axis) {
            if ($axis->isHidden() === true) {
                //add an object to the axis array also for hidden axes
                //that is necessary as hidden axes are also in the options from echart, so we need to have the same
                //ammount of axes in the new options when redrawing and calculatign the gaps, so we merge the
                //options of an axis with the correct axis and not a different (maybe hidden) one
                $axesJsObjectInit .= <<<JS
                
    axes["{$axis->getDataColumn()->getDataColumnName()}"] = {
        dimension: "{$axis->getDimension()}",
        show: false
    };
    
JS;
                continue;
            }
            
            $xAxisIndex = 0;
            if ($axis->getDimension() === Chart::AXIS_X) {
                $gap = ++$xAxisIndex . ' * 20 * 2 - 15';
                //for axes that have rotated label gap has to be calculated differently
                if ($axis->hasRotatedLabel() === true) {
                    $degree = $axis->getRotateLabelsDegree();
                    if (abs($degree) === 45) {
                        //rotation is 45 degress, therefore the gap should be the square root of
                        //2 times the square of the text length
                        $gap = 'canvasCtxt.measureText(val).width / Math.sqrt(2) + 15';                        
                    } else {
                        //rotation should be 90 degress,
                        //therefore the gap should be the text length
                        $gap = 'canvasCtxt.measureText(val).width';
                    }
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
            len = (typeof val === 'string' || val instanceof String ? val.length : (val || '').toString().length);
        }
        gap = {$gap};
        if (axes["{$axis->getDataColumn()->getDataColumnName()}"]['gap'] < gap) {
            axes["{$axis->getDataColumn()->getDataColumnName()}"]['gap'] = gap;
        }
        
JS;
            $postion = mb_strtolower($axis->getPosition());
            //if the axis has a caption the base gap is based on that length, else it's 0
            $baseGap = 0;
            if (! $axis->getHideCaption()) {
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
        rotation: {$rotated},
        show: true
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
        $firstSeries = $widget->getSeries()[0];
        $setDatasetJs = "{$this->buildJsEChartsVar()}.setOption({dataset: {source: {$dataJs}}})";
        if ($firstSeries instanceof SplittableChartSeriesInterface && $this->canSplitSeries($firstSeries)) {
            if ($firstSeries->isSplitByAttribute()) {
                $splitByDataColumnName = "'{$firstSeries->getSplitByDataColumn()->getDataColumnName()}'";
            } else {
                $splitByDataColumnName = "undefined";
            }
            $setDatasetJs = <<<JS
            
    var split = {$splitByDataColumnName};
    if (split === undefined) {
        {$this->buildJsSplitCheck($firstSeries, 'split', $dataJs)}
    }
    if (split === undefined) {
        {$setDatasetJs}
    }
    else {
        {$this->buildJsSplitSeries($firstSeries, 'split', $dataJs)}
    }
    
JS;
        }
        
        
        
        return <<<JS
        
    // initalize axis array
    var axes = [];
    {$axesJsObjectInit}
    
    // Danach
    var val, gap;
    var len = 0;
    var canvasCtxt = $('<canvas>').get(0).getContext('2d');
    canvasCtxt.font = "{$this->baseAxisLabelFontSize()}" + "px " + "{$this->baseAxisLabelFont()}";
    
    
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
        if (axis.show === false) {
            newOptions[axis.dimension + 'Axis'].push({
            });
        } else {
            if (axis.gap === 0 && {$dataJs}.length > 0) {
                {$this->buildJsShowMessageError("'{$this->getWorkbench()->getCoreApp()->getTranslator()->translate('ERROR.ECHARTS.AXIS_NO_DATA')} \"' + axis.name + '\"'")}
            }
            //if the caption for axis is shown the gap for x Axes needs to be
            // set based on the axis.gap (means the space needed to show axis values)
            if (axis.rotation === true && axis.caption === true) {
                var nameGap = axis.gap + {$this->baseAxisNameGap()};
                newOptions[axis.dimension + 'Axis'].push({
                    show: true,
                    offset: offsets[axis.position],
                    nameGap: axis.gap,
                });
                offsets[axis.position] += nameGap;
            } else {
                newOptions[axis.dimension + 'Axis'].push({
                    show: true,
                    offset: offsets[axis.position],
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
    }
    
    // the grid margin at each side is the sum of each calculated axis gap for this side + the base margin
    var gridmargin = offsets;
    gridmargin['top'] += {$this->buildJsGridMarginTop()};
    gridmargin['right'] += {$this->buildJsGridMarginRight()};
    gridmargin['bottom'] += {$this->buildJsGridMarginBottom()};
    gridmargin['left'] += {$this->buildJsGridMarginLeft()};
    
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
    
    newOptions.grid = gridmargin;
    {$this->buildJsEChartsVar()}.setOption(newOptions);
    
    {$setDatasetJs}

    if ({$selectionJs} != undefined) {
        if ({$seriesIndexMarkedJs} != undefined) {
            var params = {seriesIndex: {$seriesIndexMarkedJs}}
        } else {
            var params = {seriesIndex: 0};
        }
        params.data = {$selectionJs};
        {$this->buildJsSingleClick('params')}
    } else {
        {$this->buildJsSelect()}
    }
    
    
JS;
    }
    
    /**
     * Function to check if a series can be splitted or not
     *
     * @param ChartSeries $series
     * @return bool
     */
    protected function canSplitSeries(ChartSeries $series) : bool
    {
        return $series instanceof SplittableChartSeriesInterface && $series->getIndex() === 0 && count($series->getChart()->getSeries()) === 1;
    }
    
    
    /**
     * js snippet to check if data should be split
     * only supports single series
     *
     * @return string
     */
    protected function buildJsSplitCheck(SplittableChartSeriesInterface $series, string $splitJs, string $dataJs) : string
    {
        if (! $series instanceof XYChartSeriesInterface) {
            return '';
        }
        if (($series) instanceof BarChartSeries) {
            $axisKey = $series->getYAxis()->getDataColumn()->getDataColumnName();
        } else {
            $axisKey = $series->getXAxis()->getDataColumn()->getDataColumnName();
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
            for (var k = 0; k < doubleValues.length; k++) {
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
            {$splitJs} = dataKeys[j]
            break
        }
    }
    
JS;
            
    }
    
    /**
     * js snippet to split the dataset and configure series for each dataset part
     *
     * @return string
     */
    protected function buildJsSplitSeries(SplittableChartSeriesInterface $series, string $splitJs, string $dataJs) : string
    {
        $baseColor = 'undefined';
        if ($series instanceof iHaveColor) {
            if ($series->getColor()) {
                $baseColor = $series->getColor();
            }
        }
        $useGradients = 'true';
        if ($series->getSplitWithColorGradients() === false) {
            $useGradients = 'false';
        }
        
        $nameFormatterJs = 'newNames[i];';
        $customCol = 'false';
        $semanticColors = $this->getFacade()->getSemanticColors();
        $semanticColorsJs = json_encode(empty($semanticColors) ? new \stdClass() : $semanticColors);
        if ($series->isSplitByAttribute()) {
            $col = $series->getSplitByDataColumn();
            $nameFormatterJs = <<<JS

        function(value) {
                    return {$this->buildJsLabelFormatter($col, 'value')}
                }(newNames[i]);

JS;
            if ($col->getCellWidget() instanceof iHaveColorScale) {
                $customCol = 'true';
                $colJs = <<<JS

sColor = {$this->buildJsScaleResolver('value', $col->getCellWidget()->getColorScale(), $col->getCellWidget()->isColorScaleRangeBased())};
JS;
                
            }
        
        }
        return <<<JS
        
    var baseColor = '{$baseColor}';
    var splitDatasetObject = {};
    var useGradients = {$useGradients};
    var customCol = {$customCol};
    for (var i=0; i < {$dataJs}.length; i++) {
        var p = {$dataJs}[i][{$splitJs}];
        if (!splitDatasetObject[p]) {
            splitDatasetObject[p] = [];
        }
        splitDatasetObject[p].push({$dataJs}[i]);
    }
    var splitDatasetArray = Object.keys(splitDatasetObject).map(i => splitDatasetObject[i]);
    var newNames = Object.keys(splitDatasetObject);
    var formatNames = [];
    for (var i = 0; i < newNames.length; i++) {
        var formatted = {$nameFormatterJs}
        formatNames.push(exfTools.string.htmlUnescape !== undefined ? exfTools.string.htmlUnescape(formatted) : formatted);
    }
    //newNames = formatNames;
    if (baseColor == 'undefined') {
        var options = {$this->buildJsEChartsVar()}.getOption();
        baseColor = options['color'][{$series->getIndex()}]
    }
    var baseSeries = {$this->buildJsChartSeriesConfig($series)}
    var currentSeries = JSON.parse(JSON.stringify(baseSeries));
    currentSeries.name = formatNames[0];
    currentSeries.datasetIndex = 0;
    var gradient;
    var colorsRgb;
    var sColor;
    var value;
    if (useGradients == true) {
        gradient = tinygradient([baseColor, 'white']);
        colorsRgb = gradient.rgb(newNames.length+1);
        sColor = '#' + colorsRgb[0].toHex()
        currentSeries.itemStyle = {
            color: sColor
        }
    } else if (customCol == true) {
        var oSemanticColors = $semanticColorsJs;
        value = newNames[0];
        $colJs
        if (sColor.startsWith('~')) {
            sColor = oSemanticColors[sColor] || '';
        }
        if (sColor !== '' && sColor !== undefined && sColor !== 'undefined') {
            currentSeries.itemStyle = {
                color: sColor
            }
        }
    }
    var formatter = undefined;
    if (baseSeries.label !== undefined && baseSeries.label.formatter !== undefined) {
        formatter = baseSeries.label.formatter
        currentSeries.label.formatter = formatter;
    }
    var markLineFormatter = undefined;
    if (baseSeries.markLine !== undefined && baseSeries.markLine.label !== undefined && baseSeries.markLine.label.formatter !== undefined) {
        markLineFormatter = baseSeries.markLine.label.formatter
        currentSeries.markLine.label.formatter = markLineFormatter;
    }
    var newSeriesArray = [currentSeries];
    
    for (var i = 1; i < formatNames.length; i++) {
        currentSeries = JSON.parse(JSON.stringify(baseSeries));
        currentSeries.name = formatNames[i];
        currentSeries.datasetIndex = i;
        if (useGradients == true) {        
            sColor = '#' + colorsRgb[i].toHex();
            currentSeries.itemStyle = {
                color: sColor
            }
        } else if (customCol == true) {
            var oSemanticColors = $semanticColorsJs;
            value = newNames[i];
            $colJs
            if (sColor.startsWith('~')) {
                sColor = oSemanticColors[sColor] || '';
            }
            if (sColor !== '' && sColor !== undefined && sColor !== 'undefined') {
                currentSeries.itemStyle = {
                    color: sColor
                }
            }
        }
        if (formatter !== undefined) {
            currentSeries.label.formatter = formatter;
        }
        if (markLineFormatter !== undefined) {
            currentSeries.markLine.label.formatter = markLineFormatter;
        }
        newSeriesArray.push(currentSeries);
    }
    var dataset = [{source: splitDatasetArray[0]}]
    for (var i = 1; i < formatNames.length; i++) {
        var set = {};
        set.source = splitDatasetArray[i];
        dataset.push(set);
    }
    var newOptions = {
        dataset: dataset,
        series: newSeriesArray
    }
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
        var item = {
            value: {$dataJs}[i].{$this->getWidget()->getSeries()[0]->getValueDataColumn()->getDataColumnName()} ,
            name: {$this->buildJsLabelFormatter($this->getWidget()->getSeries()[0]->getTextDataColumn(), "{$dataJs}[i].{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()}")} ,
            _key: {$dataJs}[i].{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()}
        };
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
    if ({$selection} != undefined) {
        var index = function(){
            for (var i = 0; i < chartData.length; i++) {
                if (chartData[i].name === {$selection}.{$this->getWidget()->getSeries()[0]->getTextDataColumn()->getDataColumnName()}) {
                    return i
                }
            }
        }()
        var params = {seriesIndex: 0, dataIndex: index};
        params.data = {name: chartData[index].name};
        {$this->buildJsSingleClick('params')}
    }  else {
        {$this->buildJsSelect()}
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
        /* @var $series \exface\Core\Widgets\Parts\Charts\GraphChartSeries */
        $series = $this->getWidget()->getSeries()[0];
        
        // we only check relations in regular direction	to data, so arrows are always in the right direction in the graph
        // if relation direction is "regular" left object is source node, right object is target node for that relation
        $getSourceAndTargetFromRowJs = <<<JS
        
    		source = {$dataJs}[i].{$series->getLeftObjectDataColumn()->getDataColumnName()};
    		target = {$dataJs}[i].{$series->getRightObjectDataColumn()->getDataColumnName()};
    		
JS;
        if ($series->hasDirectionColumn()) {
            $getSourceAndTargetFromRowJs = "if (oRow.{$series->getDirectionDataColumn()->getDataColumnName()} == 'regular') {" . $getSourceAndTargetFromRowJs . "}";
        }
        
        if ($series->hasCategories() === true) {
            $categories = <<<JS
            
        var existingCategory = false;
        var categoriesIndex = undefined;
        for (var j = 0; j<categories.length; j++) {
            if (categories[j].name === {$dataJs}[i].{$series->getCategoryDataColumn()->getDataColumnName()}) {
                existingCategory = true;
                categoriesIndex = j;
            }
        }
        if (existingCategory === true) {
            var nodeCategory = categoriesIndex;
        } else {
            categories.push({name: {$dataJs}[i].{$series->getCategoryDataColumn()->getDataColumnName()} });
            var nodeCategory = categories.length-1;
        }
JS;
            
        } else {
            $categories = <<<JS
        if (categories.length === 0) {
            categories.push({name: 'Nodes'});
        }
        var nodeCategory = categories.length-1;
        
JS;
        }
        return <<<JS
        
    var nodes = [];
    var links = [];
    var node = {};
    var link = {};
    var categories = [];
    var oRow = [];
    var source, target, existingNodeLeft, existingNodeRight, existingLink;
    
    // for each data object add a node that's not already existing to the nodes array
    // and a link that's not already existing to the links array
    for (var i = 0; i < {$dataJs}.length; i++) {
        oRow = {$dataJs}[i];
		existingNodeLeft = false;
        existingNodeRight = false;
        for (var j = 0; j<nodes.length; j++) {
            // if the right object already exists at node, increase the symbol size of that node
			if (nodes[j].id === oRow.{$series->getRightObjectDataColumn()->getDataColumnName()}) {
				existingNodeRight = true;
                nodes[j].symbolSize += 1;
                nodes[j].value += 1;
			}
            // if the left object already exists at node, increase the symbol size of that node
			if (nodes[j].id === oRow.{$series->getLeftObjectDataColumn()->getDataColumnName()}) {
				existingNodeLeft = true;
                nodes[j].symbolSize += 1;
                nodes[j].value += 1;
			}
		}
        // if the left and right object are the same and not yet existing as node, only add the left object to the nodes
        if (oRow.{$series->getRightObjectDataColumn()->getDataColumnName()} === oRow.{$series->getLeftObjectDataColumn()->getDataColumnName()}) {
            existingNodeRight = true;
        }
        
        //build categories array and set category for the node
        {$categories}
        
        // if the left object is not existing as node yet, add it
		if (existingNodeLeft === false ) {
			node = {
				id: oRow.{$series->getLeftObjectDataColumn()->getDataColumnName()},
				name: oRow.{$series->getLeftObjectNameDataColumn()->getDataColumnName()},
                symbolSize: 10,
				value: 10,
                category: nodeCategory,
			};
			nodes.push(node);
		}
        // if the right object is not existing as node yet, add it
		if (existingNodeRight === false ) {
			node = {
				id: oRow.{$series->getRightObjectDataColumn()->getDataColumnName()},
				name: oRow.{$series->getRightObjectNameDataColumn()->getDataColumnName()},
				symbolSize: 10,
				value: 10,
                category: nodeCategory,
			};
	        nodes.push(node);
		}
		
        {$getSourceAndTargetFromRowJs}
        
        existingLink = false;
        // for every relation check if it's not already existing in links array
        for (var j = 0; j<links.length; j++) {
            if (links[j].id === oRow.{$series->getRelationDataColumn()->getDataColumnName()}) {
                existingLink = true;
            }
        }
        // if relation is not existing yet as link, add it to links array
        if (existingLink === false) {
            link = {
        		id: oRow.{$series->getRelationDataColumn()->getDataColumnName()},
        		name: oRow.{$series->getRelationNameDataColumn()->getDataColumnName()},
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
            categories: categories,
    	}],
    });
    if ({$selection} != undefined) {
        var index = function(){
            for (var i = 0; i < nodes.length; i++) {
                if (nodes[i].id === {$selection}.{$this->getWidget()->getSeries()[0]->getLeftObjectDataColumn()->getDataColumnName()}) {
                    return i
                }
            }
        }()
        var params = {seriesIndex: 0, dataIndex: index, dataType: 'node'};
        params.data = {id: nodes[index].id};
        {$this->buildJsSingleClick('params')}
    } else {
        {$this->buildJsSelect()}
    }
    
JS;
    }
    
    /**
     * javascript snippet to transform data to match data required for sankey charts and draw sankey chart
     * TODO implement selection keeping on redraw
     *
     * @return string
     */
    protected function buildJsRedrawSankey(string $selection = 'undefined', string $dataJs = 'rowData')
    {
        /* @var $series \exface\Core\Widgets\Parts\Charts\SankeyChartSeries */
        $series = $this->getWidget()->getSeries()[0];
        $linkCaption = 'undefined';
        if ($series->hasLinkCaptionColumn()) {
            $linkCaption = $series->getLinkCaptionAttributeDataColumn()->getDataColumnName();
        }
        return <<<JS

        var targetIdColumn = '{$series->getTargetIdAttributeDataColumn()->getDataColumnName()}';
        var targetCaption = '{$series->getTargetCaptionAttributeDataColumn()->getDataColumnName()}';
        var targetLevel = '{$series->getTargetLevelAttributeDataColumn()->getDataColumnName()}';
        var sourceIdColumn = '{$series->getSourceIdAttributeDataColumn()->getDataColumnName()}';
        var sourceCaption = '{$series->getSourceCaptionAttributeDataColumn()->getDataColumnName()}';
        var sourceLevel = '{$series->getSourceLevelAttributeDataColumn()->getDataColumnName()}';        
        var linkCaption = '{$linkCaption}';
        
        var nodes = {};
        var links = [];        
        var echart = {$this->buildJsEChartsVar()};
        var options = echart.getOption();
        var colors = options['color'];
        var minDepth;
        {$dataJs}.forEach(function(row) {           
            var sourceID = row[sourceIdColumn];
            var targetID = row[targetIdColumn];
            var depth;
            // wrap captions after 32 characers
            row[sourceCaption] = (row[sourceCaption] || '').replace(/(?![^\\n]{1,32}$)([^\\n]{1,32})\\s/g, '$1\\n');
            row[targetCaption] = (row[targetCaption] || '').replace(/(?![^\\n]{1,32}$)([^\\n]{1,32})\\s/g, '$1\\n');
            row[sourceLevel] = parseFloat(row[sourceLevel]);
            row[targetLevel] = parseFloat(row[targetLevel]);
            // if targetID and sourceID are set, add nodes
            if (targetID != '' && sourceID != '') {
                // if source doesnt exist as node yet, add it
                if (nodes[sourceID] === undefined) {
                    nodes[sourceID] = {
                        "name": row[sourceCaption],
                        "depth": row[sourceLevel],
                        "itemStyle": {
                            "color": colors[row[sourceLevel]]
                        },
                        "_caption": row[sourceCaption]
                    };
                    if (minDepth === undefined || row[sourceLevel] < minDepth) {
                        minDepth = row[sourceLevel];
                    }
                }
                // if target doesnt exist as node yet, add it
                if (nodes[targetID] === undefined) {
                    nodes[targetID] = {
                        "name": row[targetCaption],
                        "depth": row[targetLevel],
                        "itemStyle": {
                            "color": colors[row[targetLevel]]
                        },
                        "_caption": row[targetCaption]
                    };
                    if (minDepth === undefined || row[targetLevel] < minDepth) {
                        minDepth = row[targetLevel];
                    }
                }                
                
            }
            if (sourceID !== targetID && nodes[sourceID] && nodes[targetID]) {           
                var depthSource = nodes[sourceID]["depth"];
                var depthTarget = nodes[targetID]["depth"];
                //if target nodes depth higher or equal (should not happen) to source node depth add the link
                if (depthTarget >= depthSource) {
                    if (depthTarget === depthSource && ! isNaN(depthTarget)) {
                        depthTarget = depthTarget + 0.5;
                        nodes[targetID].depth = depthTarget;
                    }
                    var link = {
                        source: row[sourceCaption],
                        target: row[targetCaption],
                        value: 1,
                        lineStyle: {
                            color: colors[depthSource],
                            opacity: 0.3
                        }
                    }
                    if (linkCaption != 'undefined') {
                        link._caption = row[linkCaption];
                    }
                    links.push(link);
                }
            
                //if target node depth is higher than source node depth, add link but switch target and source 
                if (depthTarget < depthSource) {
                    var link = {
                        source: row[targetCaption],
                        target: row[sourceCaption],
                        value: 1,
                        lineStyle: {
                            color: colors[depthSource]
                        }
                    }
                    if (linkCaption != 'undefined') {
                        link._caption = row[linkCaption];
                    }
                    links.push(link);
                }
            }
        });

        if (minDepth > 0) {
            for (var i in nodes) {
                nodes[i].depth = nodes[i].depth - minDepth;
            }
        }
        
        var nodesArray = [];
        for (var prop in nodes) {
            nodesArray.push(nodes[prop]);
        }
        echart.setOption({
        	series: [{
        		data: nodesArray,
                links: links
        	}],
        });

JS;
    }
    
    /**
     * Returns a JS snippet (with a trailing `;`) to show an overlay with a given message
     *
     * The method can be overridden in facade-specific implementations.
     *
     * @param string $message
     * @return string
     */
    protected function buildJsMessageOverlayShow(string $message) : string
    {
        return <<<JS
{$this->buildJsMessageOverlayHide()}      
$({$this->buildJsEChartsVar()}.getDom()).prepend($('<div class="{$this->getId()}_exf-chart-message" style="position: absolute; padding: 10px; width: 100%; text-align: center;">{$message}</div>'));

JS;
    }
    
    /**
     * Returns a JS snippet to hide overlay message
     *
     * @return string
     */
    protected function buildJsMessageOverlayHide() : string
    {
        return <<<JS
if ($(".{$this->getId()}_exf-chart-message").length > 0) {
    $(".{$this->getId()}_exf-chart-message").remove();
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
            if ($axis->getPosition() === ChartAxis::POSITION_LEFT && $axis->isHidden() === false && ! $axis->getHideCaption()) {
                $countAxisLeft++;
            } elseif ($axis->getPosition() === ChartAxis::POSITION_RIGHT && $axis->isHidden() === false && ! $axis->getHideCaption()) {
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
        
        if ($this->isLegendHidden() === false && ($widget->getLegendPosition() === 'top' || $widget->getLegendPosition() === null)) {
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
            if ($axis->getPosition() === ChartAxis::POSITION_RIGHT && $axis->isHidden() === false) {
                $rightAxis = true;
            }
        }
        if ($rightAxis === true) {
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
        if ($this->isLegendHidden() === false && $widget->getLegendPosition() === 'bottom') {
            $margin += $this->baseLegendOffset();
        }
        $hasVisualMap = false;
        foreach ($this->getWidget()->getSeries() as $s) {
            if ($s instanceof iHaveVisualMapChartPart && $s->hasVisualMap() === true && $s->getVisualMap()->getShowScaleFilter() === true) {
                $hasVisualMap = true;
                break;
            }
        }
        if ($hasVisualMap === true) {
            $margin += $this->baseZoomOffset();
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
            if ($axis->getPosition() === ChartAxis::POSITION_LEFT && $axis->isHidden() === false) {
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
    
    protected function getChartType() : string
    {
        $s = $this->getWidget()->getSeries()[0];
        switch (true) {
            case $s instanceof PieChartSeries:
            case $s instanceof DonutChartSeries:
            case $s instanceof RoseChartSeries:
                return $this->chartTypes['CHART_TYPE_PIE'];
            case $s instanceof GraphChartSeries:
                return $this->chartTypes['CHART_TYPE_GRAPH'];;
            case $s instanceof HeatmapChartSeries:
                return $this->chartTypes['CHART_TYPE_HEATMAP'];
            case $s instanceof SankeyChartSeries:
                return $this->chartTypes['CHART_TYPE_SANKEY'];
            default:
                return $this->chartTypes['CHART_TYPE_XY'];
        }
    }
    
    /**
     * function to check if pie is a pie series
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
    
    protected function isHeatmapChart() : bool
    {
        if ($this->getWidget()->getSeries()[0] instanceof HeatMapChartSeries) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * build basic tooltip configuration
     *
     * Fix for tooltip position taken from here: https://github.com/apache/echarts/issues/5004
     *
     * @return string
     */
    protected function buildJsChartPropertyTooltip() : string
    {
        // Best-fit tooltip position to avoid overflowing the chart container
        $fnPositionJs = <<<JS
function(canvasMousePos, params, tooltipDom, rect, sizes) {
        var echartsDom = tooltipDom.closest('.exf-chart');
        var margin = 2; // How far away from the mouse should the tooltip be
        var overflowMargin = 5; // If no satisfactory position can be found, how far away from the edge of the window should the tooltip be kept
        
        var canvasRect = tooltipDom.parentElement.getElementsByTagName("canvas")[0].getBoundingClientRect();
        
        // The mouse coordinates relative to the whole window
        // The first parameter to the position function is the mouse position relative to the canvas
        var mouseX = canvasMousePos[0] + canvasRect.x;
        var mouseY = canvasMousePos[1] + canvasRect.y;
        
        // The width and height of the tooltip dom element
        var tooltipWidth = sizes.contentSize[0];
        var tooltipHeight = sizes.contentSize[1];
        
        // Start by placing the tooltip top and right relative to the mouse position
        var xPos = mouseX + margin;
        var yPos = mouseY - margin - tooltipHeight;
        
        if (echartsDom) {
            // The tooltip is overflowing past the right edge of the window
            if (Math.abs(echartsDom.clientWidth - canvasMousePos[0]) < tooltipWidth) {
                // Attempt to place the tooltip to the left of the mouse position
                xPos = mouseX - margin - tooltipWidth;
                
                // The tooltip is overflowing past the left edge of the window
                if (xPos <= 0)
                    // Place the tooltip a fixed distance from the left edge of the window
                    xPos = overflowMargin;
            }
            
            // The tooltip is overflowing past the top edge of the window
            if (yPos <= 0) {
                // Attempt to place the tooltip to the bottom of the mouse position
                yPos = mouseY + margin;
                
                // The tooltip is overflowing past the bottom edge of the window
                if (yPos + tooltipHeight >= echartsDom.clientHeight)
                    // Place the tooltip a fixed distance from the top edge of the window
                    yPos = overflowMargin;
            }
        }
        
        // Return the position (converted back to a relative position on the canvas)
        return [xPos - canvasRect.x, yPos - canvasRect.y];
    }
JS;
        switch ($this->getChartType()) {
            case $this->chartTypes['CHART_TYPE_PIE']:
                return <<<JS
                
{
	trigger: 'item',
	formatter: "{b} : {c} ({d}%)",
    confine: true,
    position: $fnPositionJs,
},

JS;
            case $this->chartTypes['CHART_TYPE_GRAPH']:
                return <<<JS
                
{
	formatter: function(params) {
		return params.data.name
	},
    confine: true,
    position: $fnPositionJs,
},

JS;
            case $this->chartTypes['CHART_TYPE_HEATMAP']:
                $series = $this->getWidget()->getSeries()[0];
                $xAxisCaption = $series->getXAxis()->getCaption();
                $xAxisName = $series->getXAxis()->getDataColumn()->getDataColumnName();
                $yAxisCaption = $series->getYAxis()->getCaption();
                $yAxisName = $series->getYAxis()->getDataColumn()->getDataColumnName();
                $valueName = $series->getValueDataColumn()->getDataColumnName();
                $valueCaption = $series->getCaption();
                return <<<JS
                
{
	formatter: function(params) {
        var xAxisName = '{$xAxisName}';
        var yAxisName = '{$yAxisName}';
        var valueName = '{$valueName}';
        var xAxisCaption = '{$xAxisCaption}';
        var yAxisCaption = '{$yAxisCaption}';
        var valueCaption = '{$valueCaption}';
        var xAxisValue = params.data[xAxisName];
        var xFormatter = function(a) {
                return {$this->buildJsLabelFormatter($series->getXAxis()->getDataColumn(), 'a')}
            };
        xAxisValue = xFormatter(xAxisValue);
        var yAxisValue = params.data[yAxisName];
        var yFormatter = function(a) {
                return {$this->buildJsLabelFormatter($series->getYAxis()->getDataColumn(), 'a')}
            };
        yAxisValue = yFormatter(yAxisValue);
        var value = params.data[valueName];
        var valueFormatter = function(a) {
                return {$this->buildJsLabelFormatter($series->getValueDataColumn(), 'a')}
            };
        value = valueFormatter(value);
        var tooltip = '<table class="exf-echarts-tooltip-table">'
        tooltip = tooltip + '<tr><td>' + xAxisCaption + '</td><td>'+ xAxisValue + '</td></tr>'
        tooltip = tooltip + '<tr><td>' + yAxisCaption + '</td><td>'+ yAxisValue + '</td></tr>'
        tooltip = tooltip + '<tr><td>' + valueCaption + '</td><td>'+ value + '</td></tr>'
        tooltip = tooltip + '</table>'
		return tooltip;
	},
    confine: true,
    position: $fnPositionJs,
},

JS;
            case $this->chartTypes['CHART_TYPE_SANKEY']:
                return <<<JS
                
{
	formatter: function(params) {
        if (params.data._caption) {
		  return params.data._caption;
        }
        return params.name;
	},
    confine: true,
    position: $fnPositionJs,
},

JS;
            default:
                return <<<JS
                
{
	trigger: 'axis',
    confine: true,
    enterable: true,
    extraCssText: 'overflow-y: auto; max-height: 50%',
	axisPointer: {
		type: 'cross'
	},
    position: $fnPositionJs,
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
        var tooltip = '<table class="exf-echarts-tooltip-table"><tr><th align = "left" colspan = "3">' + params[0].axisValueLabel + '</th></tr>';
        var tooltipPart = '';
        var currentAxis = params[0].axisIndex;
        // for each object in params build a table row
        params.forEach(function({axisIndex, axisValueLabel, marker, value, seriesIndex, seriesName}){
            var data, Index, formatter, value;
            // get the correct formatter and the data for this object in params array
            if (("_bar" in options.series[seriesIndex]) == true) {
                data = options.series[seriesIndex].encode.x;
                Index = options.series[seriesIndex].xAxisIndex;
                formatter = options.xAxis[Index].axisLabel.formatter;
            } else {
                data = options.series[seriesIndex].encode.y;
                Index = options.series[seriesIndex].yAxisIndex;
                formatter = options.yAxis[Index].axisLabel.formatter;
            }
            value = formatter(value[data]);
            if (value === null || value === undefined) {
                value = '';
            }
            // if this params object is bound to another axis as the ones before, build a new header with new label
            if (stacked === true) {
                if (axisIndex !== currentAxis) {
                    tooltip = tooltip + tooltipPart + '<tr><th colspan = "3">' + axisValueLabel + '</th></tr>';
                    currentAxis = axisIndex;
                }
                tooltipPart ='<tr><td>'+ marker + '</td><td>' + seriesName + '</td><td style="text-align: right">'+ value + '</td></tr>' + tooltipPart;
            } else {
                if (axisIndex !== currentAxis) {
                    tooltipPart += '<tr><th align = "left" colspan = "3">' + axisValueLabel + '</th></tr>';
                    currentAxis = axisIndex;
                }
                tooltip += tooltipPart + '<tr><td>'+ marker + '</td><td>' + seriesName + '</td><td style="text-align: right">'+ value + '</td></tr>';
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
        $padding = '';
        $widget = $this->getWidget();
        $firstSeries = $widget->getSeries()[0];
        $position = $widget->getLegendPosition();
        if ($this->isLegendHidden() === true) {
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
        
        /*if ($this->isLegendHidden() === true) {
            $show = 'show: false,';
        } else {
            $show = '';
        }*/
        return <<<JS
        
{
	type: 'scroll',
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
     *
     * {@inheritDoc}
     * @see exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null) : string
    {
        $widget = $this->getWidget();
        $rows = '';
        
        if ($action !== null && $action->isDefinedInWidget() && $action->getWidgetDefinedIn() instanceof DataButton) {
            $customMode = $action->getWidgetDefinedIn()->getInputRows();
        } else {
            $customMode = null;
        }
        
        switch (true) {
            case $customMode === DataButton::INPUT_ROWS_ALL:
            case $action === null:
                $rows = "{$this->buildJsEChartsVar()}._dataset";
                break;
                
            // If the button requires none of the rows explicitly
            case $customMode === DataButton::INPUT_ROWS_NONE:
                return '{}';
                
            case $action instanceof iReadData:
                // If we are reading, than we need the special data from the configurator
                // widget: filters, sorters, etc.
                return $this->getFacade()->getElement($widget->getConfiguratorWidget())->buildJsDataGetter($action);
            
            default:
                $rows = "({$this->buildJsEChartsVar()}._oldSelection ? [{$this->buildJsEChartsVar()}._oldSelection] : [])";
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
    protected function isLegendHidden() : bool
    {
        $widget = $this->getWidget();
        if ($widget->getHideLegend() === true) {
            return true;
        }
        if ($widget->getLegendPosition() !== null) {
            return false;
        }
        
        $firstSeries = $widget->getSeries()[0];
        if (count($widget->getSeries()) === 1 && (($firstSeries instanceof PieChartSeries) === false || $firstSeries instanceof GraphChartSeries === false || $firstSeries instanceof SankeyChartSeries === false)) {
            if ($firstSeries->getValueDataColumn() === $firstSeries->getValueAxis()->getDataColumn()){
                if ($firstSeries instanceof SplittableChartSeriesInterface) {
                    if ($firstSeries->isSplitByAttribute()) {
                        return false;
                    }
                }
                return true;
            } else {
                return false;
            }
        }
        return false;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getColorSchemeColors() : array
    {
        $config = $this->getFacade()->getConfig();
        if ($config->hasOption('WIDGET.CHART.COLORS')) {
            return $config->getOption('WIDGET.CHART.COLORS')->toArray();
        }
        return [];
    }
}