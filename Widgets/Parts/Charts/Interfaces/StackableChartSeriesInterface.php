<?php
namespace exface\Core\Widgets\Parts\Charts\Interfaces;

use exface\Core\Widgets\Parts\Charts\ChartSeries;

interface StackableChartSeriesInterface
{    
    public function isStacked() : ?bool;
    
    public function setStacked(bool $value) : ChartSeries;
    
    public function getStackGroupId() : ?string;
    
    public function setStackGroupId(string $group) : ChartSeries;
}