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
    
    private $showValues = null;
    
    private $showBorders = null;
    
    
    /**
     * Set to FALSE to hide the values inside a heatmap.
     *
     * @uxon-property show_values
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $value
     * @return ChartAxis
     */
    public function setShowValues(bool $trueOrFalse) : HeatmapChartSeries
    {
        $this->showValues = $trueOrFalse;
        return $this;
    }
    
    public function getShowValues() : bool
    {
        if ($this->showValues === null) {
            return true;
        }
        return $this->showValues;
    }
    
    /**
     * Set to true to show borders around values inside a heatmap.
     *
     * @uxon-property show_borders
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return ChartAxis
     */
    public function setShowBorders(bool $trueOrFalse) : HeatmapChartSeries
    {
        $this->showBorders = $trueOrFalse;
        return $this;
    }
    
    public function getShowBorders() : bool
    {
        if ($this->showBorders === null) {
            return false;
        }
        return $this->showBorders;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\Interfaces\iHaveVisualMapChartPart::hasVisualMap()
     */
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
     * ```
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
     * @uxon-type \exface\Core\Widgets\Parts\Charts\VisualMapChartPart
     * @uxon-template {"colors": ["", ""]}
     *
     * @param UxonObject $uxon
     * @return ChartSeries
     */
    public function setColorScale(UxonObject $uxon) : ChartSeries
    {
        $this->setVisualMap($uxon);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\Interfaces\iHaveVisualMapChartPart::setVisualMap()
     */
    public function setVisualMap(UxonObject $uxon) : ChartSeries
    {
        $visualMap = new VisualMapChartPart($this, $uxon);
        $this->visualMap = $visualMap;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\Interfaces\iHaveVisualMapChartPart::getVisualMap()
     */
    public function getVisualMap() : ?VisualMapChartPart
    {
        return $this->visualMap ?? new VisualMapChartPart($this);
    }
}