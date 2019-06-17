<?php
namespace exface\Core\Widgets\Parts\Charts;

class SplineChartSeries extends LineChartSeries
{

    /**
     *  {@inheritDoc}
     * @see exface\Core\Widgets\Parts\Charts\LineCartSeries::isSmooth
     */
    public function isSmooth(bool $default = true) : bool
    {
        return parent::isSmooth($default);
    }
}