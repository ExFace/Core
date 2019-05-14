<?php
namespace exface\Core\Widgets\Parts\Charts\Traits;

use exface\Core\Widgets\Parts\Charts\ChartSeries;

trait StackableChartSeriesTrait
{
    /**
     *
     * @var bool
     */
    private $stacked = false;
    
    /**
     * 
     * @return bool
     */
    public function isStacked() : bool
    {
        return $this->stacked;
    }
    
    /**
     * Set to true to stack all series of this chart
     *
     * @uxon-property stacked
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param boolean $value
     * @return ChartSeries
     */
    public function setStacked(bool $value) : ChartSeries
    {
        $this->stacked = $value;
        return $this;
    }
}