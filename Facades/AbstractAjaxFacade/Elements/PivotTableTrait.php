<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Parts\Pivot\PivotValue;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Parts\Pivot\PivotLayout;
use exface\Core\DataTypes\StringDataType;

/**
 * Common methods for facade elements based on the Pivottable.js library.
 * 
 * Make sure to include these packages in the dependecies of the facade - e.g. via composer:
 * 
 * ```
 * {
 *  "require": {
 *      "npm-asset/subtotal" : "1.11.0-alpha.0"
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
            $renderersJs = <<<JS

            renderers: {$renderersJs},
            //rendererName: '{$this->getPivotRendererSelected($layout)}',
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
        if ($layout->hasSubtotals()) {
            $name = 'Table With Subtotal';
        } else {
            $name = 'Table';
        }
        return $name;
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