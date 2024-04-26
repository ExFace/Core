<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Parts\Pivot\PivotValue;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * Common methods for facade elements based on the Pivottable.js library.
 * 
 * Make sure to include these packages in the dependecies of the facade - e.g. via composer:
 * 
 * ```
 * {
 *  "require": {
 *      "npm-asset/subtotal" : "^1"
 *  }
 * }
 * 
 * ```
 * 
 * If your facade is based on the `AbstractAjaxFacade`, add these configuration options
 * to the facade config file. Make sure, each config option points to an existing
 * include file!
 * 
 * ```
 *  "LIBS.PIVOTTABLE.CORE.JS": "npm-asset/pivottable/dist/pivot.min.js",
 * 	"LIBS.PIVOTTABLE.CORE.CSS": "npm-asset/pivottable/dist/pivot.min.css",
 * 	"LIBS.PIVOTTABLE.LANG.JS": "npm-asset/pivottable/dist/pivot.[#lang#].js",
 * 	"LIBS.PIVOTTABLE.SUBTOTAL.JS": "npm-asset/subtotal/dist/subtotal.min.js",
 * 	"LIBS.PIVOTTABLE.SUBTOTAL.CSS": "npm-asset/subtotal/dist/subtotal.min.css",
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
            $renderersJs = <<<JS

            renderers: renderers,
            rendererName: 'Table With Subtotal',
JS;
            $aggregatorsJs = '';
        } else {
            $constructorJs = 'pivot';
            $renderersJs = <<<JS

            renderer: $.pivotUtilities.subtotal_renderers['Table With Subtotal'],
            rendererOptions: {
                rowSubtotalDisplay: {
                    displayOnTop: false
                }
            },
JS;
            
            // Values
            if ($layout->hasPivotValues() === true) {
                $aggregatorsJs .= $this->buildJsAggregator($layout->getPivotValues()[0]);
            }
        }
        
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
        
        return <<<JS

    (function(jqEl, oData) {
        var dataClass = $.pivotUtilities.SubtotalPivotData;
        var renderers = $.pivotUtilities.subtotal_renderers;
        jqEl.{$constructorJs}(oData, {
            dataClass: dataClass,
            rows: {$rowsJs},
            cols: {$colsJs},
            {$renderersJs}
            {$aggregatorsJs}
        });
    })({$this->buildJsJqueryElement()}, $oDataJs)
JS;
    }
    
    protected function buildJsAggregator(PivotValue $value) : string
    {
        $key = $value->getDataColumn()->getCaption();
        switch ($value->getAggregator()) {
            case AggregatorFunctionsDataType::SUM:
                $type = $value->getDataType();
                if (! ($type instanceof NumberDataType)) {
                    throw new WidgetConfigurationError($this->getWidget(), 'Cannot use SUM aggregator on data type "' . $type->getAliasWithNamespace() . '"!');
                }
                $precision = $type->getPrecisionMin();
                $aggregator = "$.pivotUtilities.aggregatorTemplates.sum($.pivotUtilities.numberFormat({digitsAfterDecimal: $precision}))";
                break;
        }
        return "aggregator: $aggregator(['{$key}'])";
    }
}