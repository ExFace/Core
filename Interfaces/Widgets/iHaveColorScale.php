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
    public function getColor($value = null) : ?string;
    
    /**
     * Returns the color for the specified value from the color scale.
     * 
     * If no color scale is passed explicitly, the method getColorScale() will
     * be used to obtain one.
     * 
     * The color scale must be an array with numeric keys in the following structure.
     *
     * [
     *  key1 => color_for_values_less_or_equal_to_key1,
     *  key2 => color_for_values_greater_than_key1_but_less_or_equal_to_key2,
     *  ...
     * ]
     *
     * @param float $value
     * @param array $colorMap
     * @return string
     */
    public static function findColor($value, array $colorMap = null) : string;
}