<?php
namespace exface\Core\Widgets\Parts\Charts\Traits;

use exface\Core\Widgets\Parts\Charts\ChartSeries;

trait FillableChartSeriesTrait
{
    /**
     *
     * @var bool
     */
    private $filled = false;
    
    /**
     *
     * @return bool
     */
    public function isFilled() : bool
    {
        return $this->filled;
    }
    
    /**
     * Set if line/spline series should be filled or not.
     * 
     * @uxon-property filled
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return ChartSeries
     */
    public function setFilled(bool $value) : ChartSeries
    {
        $this->filled = $value;
        return $this;
    }
}