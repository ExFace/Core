<?php
namespace exface\Core\Widgets\Parts\Charts\Traits;

use exface\Core\Widgets\Parts\Charts\ChartSeries;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

trait VisualMapChartSeriesTrait
{
    static $VISUAL_MAP_TYPE_CONTINUOUS = 'continuous';
    static $VISUAL_MAP_TYPE_PIECEWISE = 'piecewise';
    
    private $visualMap = null;
    private $visualMapType = null;
    private $visualMapMin = 0;
    private $visualMapMax = 100;
    private $visualMapSplitNumber = 5;
    private $visualMapDragable = false;
    
    /**
     * function to check if series has a visualMap
     * 
     * @return bool
     */
    public function hasVisualMap() : bool
    {
        if ($this->visualMap === null) {
            return false;
        }
        return $this->visualMap;
    }
    
    /**
     * Set the visualMap type. Possible types are 'continuous'and 'piecewise'.
     *
     * @uxon-property visual_map_type
     * @uxon-type [continuous, piecewise]
     *
     * @param string $type
     * @return VisualMapChartSeriesTrait
     */
    public function setVisualMapType(string $type) : ChartSeries
    {
        $visualMapType = mb_strtoupper($type);
        $variable = 'self::$VISUAL_MAP_TYPE_' . $visualMapType;
        if (isset($variable)) {
            $this->visualMapType = $visualMapType;
        } else {
            throw new WidgetPropertyInvalidValueError($this->getChart(), 'Invalid visual map type "' . $visualMapType . '". Only CONTINUOUS or PIECEWISE are allowed!', '6TA2Y6A');
        }
        return $this;        
    }
    
    /**
     * get the type of the visualMap part
     * 
     * @return string
     */
    public function getVisualMapType() : string
    {
        if ($this->visualMapType === null) {
            return self::VISUAL_MAP_TYPE_CONTINUOUS;
        }
        return $this->visualMapType;
    }
    
    /**
     * set if in the visualMap part the min and max value should be dragable. Only works when `visual_map_type` is `continuous`
     * 
     * uxon-property visual_map_dragable
     * uxon-type boolean
     * uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return VisualMapChartSeriesTrait
     */
    public function setVisualMapDragable(bool $trueOrFalse) : ChartSeries
    {
        if ($this->visualMapType === null) {
            $this->visualMapType === self::VISUAL_MAP_TYPE_CONTINUOUS;
        }
        $this->visualMapDragable = $trueOrFalse;
    }
    
    /**
     * get if the visualMap part should be calculable
     * 
     * @return string
     */
    public function isVisualMapDragable() : string
    {
        return $this->visualMapDragable;
    }
    
    /**
     * Set the number of pieces the visual map should have. Only works if `visual_map_type` is `piecewise`.
     * 
     * @param int $number
     * @return VisualMapChartSeriesTrait
     */
    public function setVisualMapSplitNumber(int $number) : ChartSeries
    {
        if ($this->visualMapType === null) {
            $this->visualMapType === self::VISUAL_MAP_TYPE_PIECEWISE;
        }
        $this->visualMapSplitNumber = $number;
        return $this;
    }
    
    public function getVisualMapSplitNumber() : int
    {
        return $this->visualMapSplitNumber;
    }
    
    /**
     * set the minimal value to be displayed on the visualMap part
     * 
     * @uxon-property visual_map_min
     * @uxon-type integer
     * 
     * @param int $min
     * @return VisualMapChartSeriesTrait
     */
    public function setVisualMapMin(int $min) : ChartSeries
    {
        if ($this->visualMap === null) {
            $this->visualMap = true;
        }
        $this->visualMapMin = $min;
        return $this;
    }
    
    /**
     * 
     * @return int
     */
    public function getVisualMapMin() : int
    {
        return $this->visualMapMin; 
    }
    
    /**
     * set the maximal value to be displayed on the visualMap part
     *
     * @uxon-property visual_map_min
     * @uxon-type integer
     *
     * @param int $min
     * @return VisualMapChartSeriesTrait
     */
    public function setVisualMapMax(int $min) : ChartSeries
    {
        if ($this->visualMap === null) {
            $this->visualMap = true;
        }
        $this->visualMapMax = $min;
        return $this;
    }
    
    /**
     *
     * @return int
     */
    public function getVisualMapMax() : int
    {
        return $this->visualMapMax;
    }    
    
}