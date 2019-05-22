<?php
namespace exface\Core\Widgets\Parts\Charts;

class SplineChartSeries extends LineChartSeries
{
    private $smoothness = null;
    
    /**
     * 
     * @return float
     */
    public function getSmoothness() : ?float
    {
        return $this->smoothness;
    }
    
    /**
     * Set the smoothness of the Spline. Values between 0 and 1 allowed.
     * 
     * @uxon-property smoothness
     * @uxon-type float
     * @uxon-default 0.5
     * 
     * @param float $value
     * @return SplineChartSeries
     */
    public function setSmoothness(float $value) : SplineChartSeries
    {
        $this->smoothness = $value;
        return $this;
    }
}