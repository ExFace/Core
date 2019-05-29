<?php
namespace exface\Core\Widgets\Parts\Charts;

class SplineChartSeries extends LineChartSeries
{

    /**
     * 
     * @return float|NULL
     */
    public function isSmooth(bool $default = true) : ?bool
    {
        return parent::isSmooth($default);
    }
}