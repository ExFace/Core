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
 */
class VisualMapChartPart
{ 
    use ImportUxonObjectTrait;
    
    const VISUAL_MAP_TYPE_CONTINUOUS = 'CONTINUOUS';
    const VISUAL_MAP_TYPE_PIECEWISE = 'PIECEWISE';
    
    private $series = null;
    private $type = null;
    private $min = 0;
    private $max = 100;
    private $splitNumber = 5;
    private $dragable = false;
    private $show = true;
    private $colors = [];
    private $showExludedValues = true; 
    
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
     * Set the visualMap type. Possible types are 'continuous'and 'piecewise'.
     *
     * @uxon-property type
     * @uxon-type [continuous, piecewise]
     *
     * @param string $type
     * @return VisualMapChartPart
     */
    public function setType(string $type) : VisualMapChartPart
    {
        $type = mb_strtoupper($type);
        if (defined(__CLASS__ . '::VISUAL_MAP_TYPE_' . $type)) {
            $this->type = $type;
        } else {
            throw new WidgetPropertyInvalidValueError($this->getChartSeries()->getChart(), 'Invalid visual map type "' . $type . '". Only CONTINUOUS or PIECEWISE are allowed!', '6TA2Y6A');
        }
        return $this;        
    }
    
    /**
     * get the type of the visualMap part
     * 
     * @return string
     */
    public function getType() : string
    {
        if ($this->type === null) {
            return self::VISUAL_MAP_TYPE_CONTINUOUS;
        }
        return $this->type;
    }
    
    /**
     * set if in the visualMap part the min and max value should be dragable. Only works when `visual_map_type` is `continuous`
     * 
     * uxon-property dragable
     * uxon-type boolean
     * uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return VisualMapChartPart
     */
    public function setDragable(bool $trueOrFalse) : VisualMapChartPart
    {
        if ($this->type === null) {
            $this->type === self::VISUAL_MAP_TYPE_CONTINUOUS;
        }
        $this->dragable = $trueOrFalse;
        return $this;
    }
    
    /**
     * get if the visualMap part should be dragable
     * 
     * @return string
     */
    public function isDragable() : bool
    {
        return $this->dragable;
    }
    
    /**
     * Set the number of pieces the visual map should have. Only works if `visual_map_type` is `piecewise`.
     * Default is 5.
     * 
     * @uxon-property split_number
     * @uxon-type integer
     * 
     * @param int $number
     * @return VisualMapChartPart
     */
    public function setSplitNumber(int $number) : VisualMapChartPart
    {
        if ($this->type === null) {
            $this->type === self::VISUAL_MAP_TYPE_PIECEWISE;
        }
        if ($number > 0) {
            $this->splitNumber = $number;
        } else {
            throw new WidgetPropertyInvalidValueError($this->getChart(), 'Invalid visual map split number "' . $number . '". Only numbers higher than 0 are allowed!', '6TA2Y6A');
        }
        
        return $this;
    }
    
    public function getSplitNumber() : int
    {
        return $this->splitNumber;
    }
    
    /**
     * Set the minimal value to be displayed on the visualMap part.
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
     * Set the maximal value to be displayed on the visualMap part.
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
     * Set if the visualMap part should be shown or not. Data values will still be map to the colors if this property
     * is set to `false` and `visual_map` is set to `true`.
     * Default is `true`.
     * 
     * uxon-property show
     * uxon-property boolean
     * uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return VisualMapChartPart
     */
    public function setShow(bool $trueOrFalse) : VisualMapChartPart
    {
        $this->show = $trueOrFalse;
        return $this;
    }
    
    /**
     * Get if the visualMap part should be shown or not.
     * 
     * @return bool
     */
    public function getShow() : bool
    {
        return $this->show;
    }
    
    /**
     * Set the colors for the visualMap part. The first color will be assigned to value set in the `min` property,
     * the last  will be assigned to value set in the `max` property.
     * Every color in between will be maped to values in between.
     * If the `visual_map_type` is set to `continuous` the widget will try to apply a smooth transition between the colors.
     * If the `visual_map_type` is set to `piecewise` it is advised to give 2 colors more than the value set in the property `visual_map_split_number` as
     * by default 2 extra pieces will be added for values that are lower/higher than the values set in in the `min` / `max` properties.
     * To disable that behaviour set the property `show_excluded_values` to false.
     * If no colors are set, the Widget will choose colors according to the facade. 
     * 
     * uxon-property $colors
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
    
    /**
     * Set if values lower than min and higher than max should be shown as an extra piece in
     * a visualMap of the type `piecewise`.
     * Default is `false`.
     * 
     * uxon-property show_excluded_values
     * uxon-type boolean
     * uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return VisualMapChartPart
     */
    public function setShowExcludedValues(bool $trueOrFalse) : VisualMapChartPart
    {
        $this->showExludedValues = $trueOrFalse;
        return $this;
    }
    
    /**
     * Get if values lower than min and higher than max should be shown as an extra piece in
     * a visualMap of the type `piecewise`.
     * 
     * @return bool
     */
    public function getShowExcludedValues() : bool
    {
        return $this->showExludedValues;
    }
    
}