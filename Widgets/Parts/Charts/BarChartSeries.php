<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Widgets\Chart;

class BarChartSeries extends ColumnChartSeries
{
    protected function getValueColumnDimension() : string
    {
        return Chart::AXIS_X;
    }
}