<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\UxonObject;

/**
 * This interface contains methods to work with value-based icon scales.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iHaveIconScale extends iHaveIcon
{
    /**
     *
     * @return array
     */
    public function getIconScale() : array;
    
    /**
     *
     * @return bool
     */
    public function hasIconScale() : bool;
    
    /**
     * 
     * @param UxonObject $value
     * @return iHaveIconScale
     */
    public function setIconScale(UxonObject $valueIconPairs) : iHaveIconScale;
    
    /**
     * Returns the CSS icon code for the given value
     *
     * @param float $value
     * @return string
     */
    public function getIconForValue($value = null) : ?string;
    
    public function isIconScaleRangeBased() : bool;
    
    /**
     * Returns the icon for the specified value from the icon scale.
     * 
     * If no icon scale is passed explicitly, the method getIconScale() will
     * be used to obtain one.
     * 
     * $isRangeScale controls if the scale is interpreted as numeric or string-based.
     * 
     * Numeric scales must be an array with sortable keys in the following structure:
     *
     * [
     *  key1 => icon_for_values_less_or_equal_to_key1,
     *  key2 => icon_for_values_greater_than_key1_but_less_or_equal_to_key2,
     *  ...
     * ]
     * 
     * String-scales have the same technical structure, but are evaluated differently:
     * the values are simply compared to the scale keys (case insensitive!)
     * 
     * [
     *  key1 => icon_for_values_matching_key1,
     *  key2 => icon_for_values_matching_key2,
     *  ...
     * ]
     *
     * @param float $value
     * @param array $iconMap
     * @param bool $isRangeScale
     * 
     * @return string
     */
    public static function findIcon($value, array $iconMap = null, bool $isRangeScale) : string;
}