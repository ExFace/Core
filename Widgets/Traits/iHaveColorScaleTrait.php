<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveColorScale;

/**
 * This trait contains methods to work with value-based color scales.
 * 
 * @author Andrej Kabachnik
 *
 */
trait iHaveColorScaleTrait 
{
    private $colorScale = null;
    
    /**
     * 
     * {@inheritdoc}
     * @see iHaveColorScale::getColorScale()
     */
    public function getColorScale() : array
    {
        return $this->colorScale ?? static::getColorScaleDefault($this->getMin(), $this->getMax());
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see iHaveColorScale::hasColorScale()
     */
    public function hasColorScale() : bool
    {
        return $this->colorScale !== null;
    }
    
    /**
     * Specify a custom color scale for the widget.
     *
     * The color map must be an object with values as keys and CSS color codes as values.
     * The color code will be applied to all values between it's value and the previous
     * one. In the below example, all values <= 10 will be red, values > 10 and <= 20
     * will be colored yellow, those > 20 and <= 99 will have no special color and values 
     * starting with 100 (actually > 99) will be green.
     *
     * ```
     * {
     *  "10": "red",
     *  "20": "yellow",
     *  "99" : "",
     *  "100": "green"
     * }
     *
     * ```
     *
     * @uxon-property color_scale
     * @uxon-type color[]
     * @uxon-template {"10": "red", "20": "yellow", "99": "", "100": "green"}
     *
     * @param UxonObject $value
     * @return iHaveColorScale
     */
    public function setColorScale(UxonObject $value) : iHaveColorScale
    {
        $this->colorScale = $value->toArray();
        ksort($this->colorScale);
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see iHaveColorScale::getColor()
     */
    public function getColor($value = null) : ?string
    {
        return static::findColor($value, $this->getColorScale(), $this->isColorScaleRangeBased());
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see iHaveColorScale::findColor()
     */
    public static function findColor($value, array $colorMap = null, bool $isRangeScale = true) : string
    {
        if ($colorMap === null || $value === null) {
            $colorMap = static::getColorScaleDefault();
        }
        
        if ($isRangeScale === true) {
            ksort($colorMap);
            foreach ($colorMap as $max => $color) {
                if ($value <= $max) {
                    return $color;
                }
            }
        } else {
            foreach ($colorMap as $scaleVal => $color) {
                if (strcasecmp($value, $scaleVal)) {
                    return $color;
                }
            }
        }
        
        return $color;
    }
    
    /**
     * 
     * @return array
     */
    public static function getColorScaleDefault() : array
    {
        return [];
    }
}