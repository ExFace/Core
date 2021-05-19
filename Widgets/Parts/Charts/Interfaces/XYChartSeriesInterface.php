<?php
namespace exface\Core\Widgets\Parts\Charts\Interfaces;

use exface\Core\Widgets\Parts\Charts\ChartAxis;

interface XYChartSeriesInterface
{
    public function getXAxis() : ChartAxis;
    
    public function getYAxis() : ChartAxis;    
}