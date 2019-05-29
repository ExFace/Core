<?php
namespace exface\Core\Widgets\Parts\Charts;

class AreaChartSeries extends LineChartSeries
{
    public function isFilled(bool $default = true) : bool
    {
        return parent::isFilled($default);
    }
}