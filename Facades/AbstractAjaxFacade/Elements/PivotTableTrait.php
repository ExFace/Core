<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Parts\Pivot\PivotValue;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Parts\Pivot\PivotLayout;

/**
 * Common methods for facade elements based on the Pivottable.js library.
 * 
 * Make sure to include these packages in the dependecies of the facade - e.g. via composer:
 * 
 * ```
 * {
 *  "require": {
 *      "npm-asset/pivottable" : "^2.23"
 *  }
 * }
 * 
 * ```
 * 
 * This trait also uses the pivottable-plugin Subtotal.js, but in an unstable version (1.11.0-alpha.0),
 * so the code is included in the AbstractAjaxFacade instead of being loaded via Composer.
 * The unstable alpha dependency would otherwise prevent stable core versions from being
 * installed on environments, with `prefer-stable:true` in their composer.json.
 * 
 * If your facade is based on the `AbstractAjaxFacade`, add these configuration options
 * to the facade config file. Make sure, each config option points to an existing
 * include file!
 * 
 * ```
 *  "LIBS.PIVOTTABLE.CORE.JS": "npm-asset/pivottable/dist/pivot.min.js",
 * 	"LIBS.PIVOTTABLE.CORE.CSS": "npm-asset/pivottable/dist/pivot.min.css",
 * 	"LIBS.PIVOTTABLE.LANG.JS": "npm-asset/pivottable/dist/pivot.[#lang#].js",
 * 	"LIBS.PIVOTTABLE.SUBTOTAL.JS": "exface/core/Facades/AbstractAjaxFacade/js/subtotal/dist/subtotal.min.js",
 * 	"LIBS.PIVOTTABLE.SUBTOTAL.CSS": "exface/core/Facades/AbstractAjaxFacade/js/subtotal/dist/subtotal.min.css",
 * 	"LIBS.PIVOTTABLE.UI.JS": "npm-asset/jquery-ui/dist/jquery-ui.min.js",
 * 	"LIBS.PIVOTTABLE.UI.CSS": "npm-asset/jquery-ui/dist/themes/base/jquery-ui.min.css",
 * 	"LIBS.PIVOTTABLE.UI.THEME": "npm-asset/jquery-ui/dist/themes/base/theme.css",
 * 	
 * ```
 * 
 * NOTE: This trait requires the exfTools JS library to be available!
 * 
 * @method \exface\Core\Widgets\PivotTable getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait PivotTableTrait 
{    
    private $viewRenderers = [
        'table' => 'Table',
        'table_bar_chart' => 'Table Barchart',
        'heatmap' => 'Heatmap',
        'heatmap_per_row' => 'Row Heatmap',
        'heatmap_per_column' => 'Col Heatmap',
        'chart_bars' => 'Horizontal Bar Chart',
        'chart_bars_stacked' => 'Horizontal Stacked Bar Chart',
        'chart_columns' => 'Bar Chart',
        'chart_columns_stacked' => 'Bar Chart Stacked',
        'chart_line' => 'Line Chart',
        'chart_area' => 'Area Chart',
        'chart_pies' => 'Multiple Pie Chart',
        'export_tsv' => 'TSV Export'
    ];
    
    /**
     * 
     * @return string[]
     */
    protected function buildHtmlHeadTagsForPivot() : array
    {
        $facade = $this->getFacade();
        $includes = [
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.PIVOTTABLE.CORE.JS') . '"></script>',
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.PIVOTTABLE.SUBTOTAL.JS') . '"></script>',
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.PIVOTTABLE.UI.JS') . '"></script>',
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.PIVOTTABLE.LIBS.PLOTLY') . '"></script>',
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.PIVOTTABLE.RENDERERS.EXPORT') . '"></script>',
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.PIVOTTABLE.RENDERERS.CHARTS') . '"></script>',
            '<link href="' . $facade->buildUrlToSource('LIBS.PIVOTTABLE.CORE.CSS') . '" rel="stylesheet" media="screen">',
            '<link href="' . $facade->buildUrlToSource('LIBS.PIVOTTABLE.SUBTOTAL.CSS') . '" rel="stylesheet" media="screen">',
            '<link href="' . $facade->buildUrlToSource('LIBS.PIVOTTABLE.UI.CSS') . '" rel="stylesheet" media="screen">',
            '<link href="' . $facade->buildUrlToSource('LIBS.PIVOTTABLE.UI.THEME') . '" rel="stylesheet" media="screen">'
        ];
        // TODO Add language files
        return $includes;
    }
    
    /**
     * Returns the jQuery element for jExcel - e.g. $('#element_id') in most cases.
     * @return string
     */
    protected function buildJsJqueryElement() : string
    {
        return "$('#{$this->getId()}')";
    }
    
    /**
     * 
     * @return string
     */
    protected function buildHtmlPivot() : string
    {
        return <<<JS

<div id="{$this->getId()}" class="exf-pivottable-wrapper"></div>
JS;
    }
      
    /**
     * 
     * @return string
     */
    protected function buildJsPivotRender(string $oDataJs) : string
    {
        $widget = $this->getWidget();
        $layout = $widget->getPivotLayout();
        
        if ($widget->isDisabled() !== true) {
            $constructorJs = 'pivotUI';
            $renderers = $this->getPivotRenderersAvailable($layout);
            if (count($renderers) > 1) {
                $renderersJs = '$.extend(' . implode(',', $renderers) . ')';
            } else {
                $renderersJs = $renderers[0];
            }
            // Fire a custom event on every refresh. This will allow other
            // Pivot widgets to follow the config of this one and display the same
            // layout with another view (e.g. a chart)
            // See https://pivottable.js.org/examples/onrefresh.html
            $renderersJs = <<<JS

            renderers: {$renderersJs},
            rendererName: '{$this->getPivotRendererSelected($layout)}',
            onRefresh: function(config) {
                var oCfgCopy = JSON.parse(JSON.stringify(config));
                //delete some values which are functions
                delete oCfgCopy["aggregators"];
                delete oCfgCopy["renderers"];
                //delete some bulky default values
                delete oCfgCopy["rendererOptions"];
                delete oCfgCopy["localeStrings"];
                {$this->buildJsJqueryElement()}.trigger('pivotrendered', {
                    element_id: '{$this->getId()}',
                    object_alias: '{$this->getWidget()->getMetaObject()->getAliasWithNamespace()}',
                    config: oCfgCopy
                });
            },
JS;
            $aggregatorsJs = '';
        } else {
            $constructorJs = 'pivot';
            $renderersJs = <<<JS

            renderer: {$this->getPivotRenderersAvailable($layout)[0]}['{$this->getPivotRendererSelected($layout)}'],
JS;
            
            // Values
            if ($layout->hasPivotValues() === true) {
                $aggregatorsJs .= $this->buildJsAggregator($layout->getPivotValues()[0]);
            }
        }
        
        // Renderer options
        $rendererOptions = [];
        if ($layout->getShowColumnSubtotals() === PivotLayout::COLUMN_SUBTOTALS_BOTTOM) {
            $rendererOptions['rowSubtotalDisplay']['displayOnTop'] = false;
        }
        if ($layout->getShowColumnSubtotals() === PivotLayout::COLUMN_SUBTOTALS_NONE) {
            $rendererOptions['rowSubtotalDisplay']['disableFrom'] = 0;
        }
        if ($layout->getShowRowSubtotals() === PivotLayout::ROW_SUBTOTALS_NONE) {
            $rendererOptions['colSubtotalDisplay']['disableFrom'] = 0;
        }
        $rendererOptionsJs = empty($rendererOptions) ? '' : 'rendererOptions: ' . json_encode($rendererOptions) . ',';
        
        // Columns
        $cols = [];
        foreach ($layout->getPivotColumns() as $col) {
            $cols[] = $col->getDataColumn()->getCaption();
        }
        $colsJs = json_encode($cols);
        
        // Rows
        $rows = [];
        foreach ($layout->getPivotRows() as $row) {
            $rows[] = $row->getDataColumn()->getCaption();
        }
        $rowsJs = json_encode($rows);
        
        // CSS classes for certain options
        $cssOptionsJs = '';
        if ($layout->getShowRowTotals() === false) {
            $cssOptionsJs .= "jqEl.addClass('exf-pvt-no-row-total');\n";
        } else {
            $cssOptionsJs .= "jqEl.removeClass('exf-pvt-no-row-total');\n";
        }
        if ($layout->getShowColumnTotals() === false) {
            $cssOptionsJs .= "jqEl.addClass('exf-pvt-no-column-total');\n";
        } else {
            $cssOptionsJs .= "jqEl.removeClass('exf-pvt-no-column-total');\n";
        }
        
        if ($layout->hasSubtotals()) {
            $dataClassJs = "dataClass: $.pivotUtilities.SubtotalPivotData,";
        }
        
        return <<<JS

    (function(jqEl, oData) {
        jqEl.{$constructorJs}(oData, {
            {$dataClassJs}
            rows: {$rowsJs},
            cols: {$colsJs},
            {$renderersJs}
            {$rendererOptionsJs}
            {$aggregatorsJs}
        });
        {$cssOptionsJs}
    })({$this->buildJsJqueryElement()}, $oDataJs)
JS;
    }
    
    /**
     * 
     * @param PivotLayout $layout
     * @return array
     */
    protected function getPivotRenderersAvailable(PivotLayout $layout) : array
    {
        if ($layout->hasSubtotals()) {
            $arr = ['$.pivotUtilities.subtotal_renderers'];
        } else {
            $arr = [
                '$.pivotUtilities.renderers'
                ,'$.pivotUtilities.plotly_renderers'
                ,'$.pivotUtilities.export_renderers'
            ];
        }
        return $arr;
    }
    
    protected function getPivotRendererSelected(PivotLayout $layout) : string
    {
        $view = $layout->getView('table');
        if ($layout->hasSubtotals()) {
            switch ($view) {
                case 'table': $name = 'Table With Subtotal'; break; 
                case 'table_bar_chart': $name = 'Table With Subtotal Bar Chart'; break; 
                case 'heatmap': $name = 'Table With Subtotal Heatmap'; break; 
                case 'heatmap_per_column': $name = 'Table With Subtotal Col Heatmap'; break; 
                case 'heatmap_per_row': $name = 'Table With Subtotal Row Heatmap'; break; 
                default: $name = $this->viewRenderers[$view];
            }
        } else {
            $name = $this->viewRenderers[$view];
        }
        return $name ?? 'Table';
    }
    
    protected function buildJsAggregator(PivotValue $value) : string
    {
        $key = $value->getDataColumn()->getCaption();
        $type = $value->getDataType();
        switch (true) {
            case $type instanceof NumberDataType && $type->getBase() === 10:
                $formatterObj = [
                    "digitsAfterDecimal" => $type->getPrecisionMin() ?? 1, 
                    // "scaler": 1, // What is this?
                    "thousandsSep" => $type->getGroupSeparator() ?? '', 
                    "decimalSep" => $type->getDecimalSeparator(),
                    "prefix" => $type->getPrefix() ?? '', 
                    "suffix" => $type->getSuffix() ?? ''
                ];
                $formatterJs = "$.pivotUtilities.numberFormat(" . json_encode($formatterObj) . ")";
                break;
            default:
                $formatterJs = '';
        }
        // See https://github.com/nicolaskruchten/pivottable/blob/master/src/pivot.coffee
        $aggr = $value->getAggregator();
        switch ($aggr) {
            case AggregatorFunctionsDataType::SUM:
                if (! ($type instanceof NumberDataType)) {
                    throw new WidgetConfigurationError($this->getWidget(), 'Cannot use ' . $aggr . ' aggregator on data type "' . $type->getAliasWithNamespace() . '"!');
                }
                $aggregator = "$.pivotUtilities.aggregatorTemplates.sum({$formatterJs})";
                break;
            case AggregatorFunctionsDataType::COUNT:
                $aggregator = "$.pivotUtilities.aggregatorTemplates.count({$formatterJs})";
                break;
            case AggregatorFunctionsDataType::COUNT_DISTINCT:
                // TODO countUnique produces an infinite loop in JS for some reason... For now
                // replaced by a simple count.
                // $aggregator = "$.pivotUtilities.aggregatorTemplates.countUnique({$formatterJs})";
                $aggregator = "$.pivotUtilities.aggregatorTemplates.count({$formatterJs})";
                break;
            case AggregatorFunctionsDataType::MAX:
                $aggregator = "$.pivotUtilities.aggregatorTemplates.max({$formatterJs})";
                break;
            case AggregatorFunctionsDataType::MIN:
                $aggregator = "$.pivotUtilities.aggregatorTemplates.min({$formatterJs})";
                break;
            case AggregatorFunctionsDataType::AVG:
                if (! ($type instanceof NumberDataType)) {
                    throw new WidgetConfigurationError($this->getWidget(), 'Cannot use ' . $aggr . ' aggregator on data type "' . $type->getAliasWithNamespace() . '"!');
                }
                $aggregator = "$.pivotUtilities.aggregatorTemplates.average({$formatterJs})";
                break;
            case AggregatorFunctionsDataType::LIST_ALL:
            case AggregatorFunctionsDataType::LIST_DISTINCT:
                $aggregator = "$.pivotUtilities.aggregatorTemplates.listUnique(',')";
                break;
            default:
                throw new WidgetConfigurationError($this->getWidget(), 'Aggregator "' . $value->getAggregator() . '" not (yet) supported in disabled PivotTable widgets.');
        }
        return "aggregator: {$aggregator}(['{$key}'])";
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        return parent::buildCssElementClass() . ' exf-pivottable';
    }
}