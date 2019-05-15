<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Widgets\Chart;
/**
 * A bar chart shows horizontal bars for every value on the X axis.
 *
 * This the amount of objects for every app:
 *
 * ```
 * {
 *  "widget_type": "Chart",
 *  "object_alias": "exface.Core.APP"
 *  "data": {
 *      "paginate": false
 *  }
 *  "series": [
 *      "type": "bar",
 *      "x_attribute_alias": "OBJECT__UID:COUNT",
 *      "y_attribute_alias: "LABEL"
 *  ]
 * }
 *
 * ```
 * 
 * @author Andrej Kabachnk
 *
 */
class BarChartSeries extends ColumnChartSeries
{
    protected function getValueColumnDimension() : string
    {
        return Chart::AXIS_X;
    }
}