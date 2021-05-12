<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Widgets\Parts\Charts\Traits\XYChartSeriesTrait;
use exface\Core\Widgets\Parts\Charts\Traits\StackableChartSeriesTrait;
use exface\Core\Widgets\Parts\Charts\Interfaces\StackableChartSeriesInterface;
use exface\Core\Widgets\Parts\Charts\Interfaces\SplittableChartSeriesInterface;
use exface\Core\Widgets\Parts\Charts\Traits\SplittableChartSeriesTrait;
use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Widgets\Parts\Charts\Interfaces\XYChartSeriesInterface;

/**
 * A column chart shows vertical columns for every value on the X axis.
 * 
 * This the amount of objects for every app:
 * 
 * ```
 * {
 *  "widget_type": "Chart",
 *  "object_alias": "exface.Core.APP"
 *  "data": {
 *      "paginate": false
 *  },
 *  "series": [
 *      {
 *          "type": "column",
 *          "y_attribute_alias": "OBJECT__UID:COUNT",
 *          "x_attribute_alias": "LABEL"
 *      }
 *  ]
 * }
 * 
 * ```
 * @author Andrej Kabachnk
 *
 */
class ColumnChartSeries extends ChartSeries implements StackableChartSeriesInterface, SplittableChartSeriesInterface, iHaveColor, XYChartSeriesInterface
{
    use XYChartSeriesTrait;
    use StackableChartSeriesTrait;
    use SplittableChartSeriesTrait;
}