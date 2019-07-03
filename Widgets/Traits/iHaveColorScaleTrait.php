<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iConfigureWidgets;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
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
     * one. In the below example, all values <= 10 will be yellow, values > 10 and <= 90
     * will be colored green and values between > 90 will be gray.
     *
     * ```
     * {
     *  "10": "yellow",
     *  "90": "green",
     *  "100" : "gray"
     * }
     *
     * ```
     *
     * @uxon-property override_attribute_data_type
     * @uxon-type color[]
     * @uxon-template {"10": "yellow", "90": "green", "100" : "gray"}
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
        return static::findColor($value, $this->getColorScale());
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see iHaveColorScale::findColor()
     */
    public static function findColor($value, array $colorMap = null) : string
    {
        if ($colorMap === null || $value === null) {
            $colorMap = static::getColorScaleDefault();
        }
        
        ksort($colorMap);
        foreach ($colorMap as $max => $color) {
            if ($value <= $max) {
                return $color;
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