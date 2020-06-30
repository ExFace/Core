<?php
namespace exface\Core\Widgets\Parts\Charts\Interfaces;

use exface\Core\Widgets\Parts\Charts\ChartSeries;
use exface\Core\Widgets\Parts\Charts\VisualMapChartPart;
use exface\Core\CommonLogic\UxonObject;

interface iHaveVisualMapChartPart
{   
    public function hasVisualMap() : bool;
    
    public function setVisualMap(UxonObject $uxon) : ChartSeries;
    
    public function getVisualMap() : ?VisualMapChartPart;
}