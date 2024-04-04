<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\UxonObject;

/**
 * This trait contains methods to work with value-based color scales.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iHaveColorScale 
{
    /**
     *
     * @return array
     */
    public function getColorScale() : array;
    
    /**
     *
     * @return bool
     */
    public function hasColorScale() : bool;
    
    /**
     * 
     * @param UxonObject $value
     * @return iHaveColorScale
     */
    public function setColorScale(UxonObject $valueColorPairs) : iHaveColorScale;
    
    /**
     * Returns the CSS color code for the given value
     *
     * @param float $value
     * @return string
     */
    public function getColorForValue($value = null) : ?string;
    
    public function isColorScaleRangeBased() : bool;
    
    /**
     * Returns the color for the specified value from the color scale.
     * 
     * If no color scale is passed explicitly, the method getColorScale() will
     * be used to obtain one.
     * 
     * $isRangeScale controls if the scale is interpreted as numeric or string-based.
     * 
     * Numeric scales must be an array with sortable keys in the following structure:
     *
     * [
     *  key1 => color_for_values_less_or_equal_to_key1,
     *  key2 => color_for_values_greater_than_key1_but_less_or_equal_to_key2,
     *  ...
     * ]
     * 
     * String-scales have the same technical structure, but are evaluated differently:
     * the values are simply compared to the scale keys (case insensitive!)
     * 
     * [
     *  key1 => color_for_values_matching_key1,
     *  key2 => color_for_values_matching_key2,
     *  ...
     * ]
     *
     * @param float $value
     * @param array $colorMap
     * @param bool $isRangeScale
     * 
     * @return string
     */
    public static function findColor($value, array $colorMap = null, bool $isRangeScale) : string;
}