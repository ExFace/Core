<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Widgets\Parts\Charts\Traits\FillableChartSeriesTrait;
use exface\Core\Widgets\Parts\Charts\Traits\StackableChartSeriesTrait;
use exface\Core\Widgets\Parts\Charts\Traits\XYChartSeriesTrait;

class LineChartSeries extends ChartSeries
{
    use FillableChartSeriesTrait;
    use StackableChartSeriesTrait;
    use XYChartSeriesTrait;
}