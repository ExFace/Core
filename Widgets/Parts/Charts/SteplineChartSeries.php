<?php
namespace exface\Core\Widgets\Parts\Charts;

class SteplineChartSeries extends LineChartSeries
{

    /**
     *  {@inheritDoc}
     * @see exface\Core\Widgets\Parts\Charts\LineCartSeries::isSmooth
     */
    public function isStepline(bool $default = true) : bool
    {
        return parent::isStepline($default);
    }
}