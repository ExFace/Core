<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Widgets\Parts\Charts\Traits\FillableChartSeriesTrait;
use exface\Core\Widgets\Parts\Charts\Traits\StackableChartSeriesTrait;
use exface\Core\Widgets\Parts\Charts\Traits\XYChartSeriesTrait;
use exface\Core\Widgets\Parts\Charts\Interfaces\StackableChartSeriesInterface;

/**
 * Line chart using a meta attributes or column references for X and Y values.
 * 
 * This example shows daily sales of the last month as a stacked area chart
 * thus, visualizing the relation between costs and earnings. The example is
 * based on an imaginary meta object describing a tabular storage (e.g.
 * SQL table), where each row is a sales document with a date and price-related
 * KPIs costs, earnings and discount (price = costs + earnings + discount).
 * 
 * ```
 * {
 *  "object_alias": "my.SalesAnalyzer.sales",
 *  "widget_type": "Chart",
 *  "data": {
 *      "paginate": false,
 *      "aggregate_by_attribute_alias": "date",
 *      "filters": [
 *          {
 *              "attribute_alias": "date",
 *              "value": "-1M"
 *          }
 *      ],
 *      "sorters": [
 *          {
 *              "attribute_alias": "date",
 *              "direction": "ASC"
 *          }
 *      ]
 *  },
 *  "axis_x": [
 *      {
 *          "attribute_alias": "Date"
 *      }
 *  ],
 *  "series": [
 *      {
 *          "type": "line",
 *          "filled": true,
 *          "stacked": true,
 *          "y_attribute_alias": "costs:SUM"
 *      },
 *      {
 *          "type": "line",
 *          "filled": true,
 *          "stacked": true,
 *          "y_axis_no": 0,
 *          "y_attribute_alias": "earnings:SUM"
 *      },
 *      {
 *          "type": "line",
 *          "filled": true,
 *          "stacked": true,
 *          "y_axis_no": 0,
 *          "y_attribute_alias": "discount:SUM"
 *      }
 *  ]
 * }
 * 
 * ```
 * 
 * @author Andrej kabachnik
 *
 */
class LineChartSeries extends ChartSeries implements StackableChartSeriesInterface
{
    use FillableChartSeriesTrait;
    use StackableChartSeriesTrait;
    use XYChartSeriesTrait;
    
    private $smooth = null;
    
    private $hide_symbol = null;
    
    private $stepline = null;
    
    /**
     * 'true' when line should be smooth, 'false' when not
     * 
     * @param bool $default
     * @return bool
     */
    public function isSmooth(bool $default = false) : bool
    {
        return $this->smooth ?? $default;
    }
    
    /**
     * Set to 'true' if you want the series to have a smooth line.
     *
     * @uxon-property smooth
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return LineChartSeries
     */
    public function setSmooth(bool $value) : LineChartSeries
    {
        $this->smooth = $value;
        return $this;
    }
    
    /**
     * Set 'true' to hide datapoint symbol
     * @uxon-property hide_symbol
     * @uxon-type bool
     * 
     * @param bool $hide
     * @return LineChartSeries
     */
    public function setHideSymbol (bool $hide) : LineChartSeries
    {
        $this->hide_symbol = $hide;
        return $this;
    }
    
    /**
     * 'true' when datapoint smybols should be hidden, 'false' when they should be shown
     * 
     * @param bool $default
     * @return bool
     */
    public function isSymbolHidden (bool $default = false) : bool
    {
        return $this->hide_symbol ?? $default;
    }   
    
    /**
     * Set to 'true' if you want the series to be a stepline.
     *
     * @uxon-property stepline
     * @uxon-type boolean
     *
     * @param bool $value
     * @return LineChartSeries
     */
    public function setStepline(bool $value) : LineChartSeries
    {
        $this->stepline = $value;
        return $this;
    }
    
    /**
     * 'true' when series should be stepline, 'false' when it shouldn't
     *
     * @param bool $default
     * @return bool
     */
    public function isStepline(bool $default = false) : bool
    {
        return $this->stepline ?? $default;
    }
}