<?php
namespace exface\Core\Widgets\Parts\Charts\Interfaces;

use exface\Core\Widgets\Parts\Charts\ChartSeries;

interface SplittableChartSeriesInterface
{    
    
    
    /**
     * 
     * @param string $value
     * @return ChartSeries
     */
    public function setSplitByAttributeAlias(string $value) : ChartSeries;
    
    /**
     *
     * @return string|NULL
     */
    public function getSplitByAttributeAlias() : ?string;
    
    /**
     *
     * @return bool
     */
    public function isSplitByAttribute() : bool;
}