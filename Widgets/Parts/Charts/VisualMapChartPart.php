<?php
namespace exface\Core\Widgets\Parts\Charts;


use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;


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
 */
class VisualMapChartPart
{ 
    use ImportUxonObjectTrait;
    
    const VISUAL_MAP_TYPE_CONTINUOUS = 'CONTINUOUS';
    const VISUAL_MAP_TYPE_PIECEWISE = 'PIECEWISE';
    
    private $series = null;
    private $min = 0;
    private $max = 100;
    private $splitNumber = null;
    private $showScaleFilter = true;
    private $colors = [];
    
    public function __construct(ChartSeries $series, UxonObject $uxon = null)
    {
        $this->series = $series;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    /**
     *
     * @return ChartSeries
     */
    public function getChartSeries() : ChartSeries
    {
        return $this->series;
    }
    
    /**
     * get the type of the color scale filter
     * 
     * @return string
     */
    public function getType() : string
    {
        if ($this->splitNumber === null) {
            return self::VISUAL_MAP_TYPE_CONTINUOUS;
        }
        return self::VISUAL_MAP_TYPE_PIECEWISE;
    }
    
    /**
     * Set the number of pieces the color scale should have. Set 'false' to use a a continuous color scale.
     * Set `true` to use a default (5) count of pieces. Set a `number` to divide the range between min/max into that amount
     * of pieces.
     * 
     * @uxon-property split_number
     * 
     * @param int $number
     * @return VisualMapChartPart
     */
    public function setUseColorGroups($boolOrNumber) : VisualMapChartPart
    {        
        if (gettype($boolOrNumber) === 'boolean') {
            if ($boolOrNumber === true)
            $this->splitNumber = 5;
        } else if(gettype($boolOrNumber) === 'integer') {
            $this->splitNumber = $boolOrNumber;            
        } else {
            throw new WidgetPropertyInvalidValueError($this->getChart(), 'Invalid use_color_groups value "' . $boolOrNumber . '". Only boolean or integer higher than 0 are allowed!', '6TA2Y6A');
        }
        
        return $this;
    }
    
    public function getUseColorGroups() : ?int
    {
        return $this->splitNumber;
    }
    
    /**
     * Set the minimal value to be displayed on the color scale.
     * Default is 0.
     * 
     * @uxon-property min
     * @uxon-type integer
     * 
     * @param int $min
     * @return VisualMapChartPart
     */
    public function setMin(int $min) : VisualMapChartPart
    {
        $this->min = $min;
        return $this;
    }
    
    /**
     * 
     * @return int
     */
    public function getMin() : int
    {
        return $this->min; 
    }
    
    /**
     * Set the maximal value to be displayed on the color scale.
     * Default is 100.
     *
     * @uxon-property max
     * @uxon-type integer
     *
     * @param int $min
     * @return VisualMapChartPart
     */
    public function setMax(int $max) : VisualMapChartPart
    {
        $this->max = $max;
        return $this;
    }
    
    /**
     *
     * @return int
     */
    public function getMax() : int
    {
        return $this->max;
    }
    
    /**
     * Set if the color scale filter should be shown or not.
     * Data values will still be mapped to the colors if this property is set to `false`.
     * Default is `true`.
     * 
     * uxon-property show_scale_filter
     * uxon-property boolean
     * uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return VisualMapChartPart
     */
    public function setShowScaleFilter(bool $trueOrFalse) : VisualMapChartPart
    {
        $this->showScaleFilter = $trueOrFalse;
        return $this;
    }
    
    /**
     * Get if the color scale filter should be shown or not.
     * 
     * @return bool
     */
    public function getShowScaleFilter() : bool
    {
        return $this->showScaleFilter;
    }
    
    /**
     * Set the colors for the color scale filter. The first color will be assigned to value set in the `min` property,
     * the last  will be assigned to the `max` value or, if `use_color_groups` is `true` or an integer, to the group
     * for values higher than `max`. Every color in between will be maped to values in between.
     * If the `use_color_groups` property is set to a number it is advised to as many colors as `use_color_groups` is set to plus
     * and additional one, because one extra pieces will be added for values that are higher than the values set in in the `max` property.
     * The values in the min/max range will be split into as many groups as given in the `use_color_groups` property.
     * If no colors are set, the Widget will choose colors according to the facade. 
     * 
     * uxon-property colors
     * uxon-type array
     * 
     * @param UxonObject $colors
     * @return VisualMapChartPart
     */
    public function setColors(UxonObject $colors) : VisualMapChartPart
    {
        $this->colors = $colors->toArray();
        return $this;
    }
    
    /**
     * Get the colors for the visualMap part
     * 
     * @return array
     */
    public function getColors() : array
    {
        return $this->colors;
    }
    
}