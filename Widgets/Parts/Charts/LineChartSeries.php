<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Widgets\Parts\Charts\Traits\FillableChartSeriesTrait;
use exface\Core\Widgets\Parts\Charts\Traits\StackableChartSeriesTrait;
use exface\Core\Widgets\Parts\Charts\Traits\XYChartSeriesTrait;

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
 *    "paginate": false,
 *    "aggregate_by_attribute_alias": "date",
 *    "filters": [
 *      {
 *    "attribute_alias": "date",
 *    "value": "-1M"
 *      }
 *    ],
 *    "sorters": [
 *      {
 *    "attribute_alias": "date",
 *    "direction": "ASC"
 *      }
 *    ]
 *  },
 *  "axis_x": [
 *    {
 *      "attribute_alias": "Date"
 *    }
 *  ],
 *  "series": [
 *    {
 *      "type": "line",
 *      "filled": true,
 *      "stacked": true,
 *      "y_attribute_alias": "costs:SUM"
 *    },
 *    {
 *      "type": "line",
 *      "filled": true,
 *      "stacked": true,
 *      "y_axis_no": 0,
 *      "y_attribute_alias": "earnings:SUM"
 *    },
 *    {
 *      "type": "line",
 *      "filled": true,
 *      "stacked": true,
 *      "y_axis_no": 0,
 *      "y_attribute_alias": "discount:SUM"
 *    }
 *  ]
 * }
 * 
 * ```
 * 
 * @author Andrej kabachnik
 *
 */
class LineChartSeries extends ChartSeries
{
    use FillableChartSeriesTrait;
    use StackableChartSeriesTrait;
    use XYChartSeriesTrait;
}