<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\Charts\Traits\XYChartSeriesTrait;
use exface\Core\Widgets\Parts\Charts\Interfaces\iHaveVisualMapChartPart;

/**
 
 */
class HeatmapChartSeries extends ChartSeries implements iHaveVisualMapChartPart
{
    use XYChartSeriesTrait;
    
    private $visualMap = null;
    
    public function hasVisualMap(): bool
    {        
        return true;
    }

    /**
     * Adds a visualMap part to the Chart. The visualMap part maps colors to chart values and by default shows a visualMap
     * either as pieces or as a continuous strip.
     *
     * Example:
     *
     * ```json
     * series[
     *  {
     *      "type": "heatmap",
     *      "color_scale": {
     *          "min": 0,
     *          "max": 40,
     *          "use_color_groups": 5,
     *          "colors": ['green', 'yellow', 'red'],
     *          "show_scale_filter": false
     *      }
     *  }
     * ]
     *
     * ```
     *
     * @uxon-property color_scale
     * @uxon-type exface\Core\Widgets\Parts\Charts\VisualMapChartPart
     * @uxon-template {"min": "", "max": ""}
     *
     * @param UxonObject $uxon
     * @return ChartSeries
     */
    public function setColorScale(UxonObject $uxon) : ChartSeries
    {
        $this->setVisualMap($uxon);
        return $this;
    }
    
    public function setVisualMap(UxonObject $uxon) : ChartSeries
    {
        $visualMap = new VisualMapChartPart($this, $uxon);
        $this->visualMap = $visualMap;
        return $this;
    }
    
    public function getVisualMap() : ?VisualMapChartPart
    {
        return $this->visualMap ?? new VisualMapChartPart($this);
    }
    
}