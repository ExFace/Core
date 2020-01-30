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
        if ($this->visualMap === null) {
            return false;
        }
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
     *      "visual_map": {
     *          "type": "continuous",
     *          "min": 0,
     *          "max": 40,
     *          "colors": ['blue', 'yellow', 'red'],
     *          "show": true
     *      }
     *  }
     * ]
     *
     * ```
     *
     * @uxon-property visual_map
     * @uxon-type exface\Core\Widgets\Parts\Charts\VisualMapCahrtPart
     * @uxon-template {"type": "", "min": "", "max": ""}
     *
     * @param UxonObject $uxon
     * @return ChartSeries
     */
    public function setVisualMap(UxonObject $uxon) : ChartSeries
    {
        $visualMap = new VisualMapChartPart($this, $uxon);
        $this->visualMap = $visualMap;
        return $this;
    }
    
    public function getVisualMap() : ?VisualMapChartPart
    {
        return $this->visualMap;
    }
    
}