<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Widgets\Parts\Charts\Traits\XYChartSeriesTrait;
use exface\Core\Widgets\Parts\Charts\Traits\StackableChartSeriesTrait;

class ColumnChartSeries extends ChartSeries
{
    use XYChartSeriesTrait;
    use StackableChartSeriesTrait;
}