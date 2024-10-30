<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 * Interface for scale-based widget properties - e.g. color_scale, hint_scale, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
interface WidgetPropertyScaleInterface extends iCanBeConvertedToUxon, WidgetPartInterface
{
    /**
     * 
     * @return array
     */
    public function getScaleValues() : array;
    
    /**
     * 
     * @return bool
     */
    public function isEmpty() : bool;
    
    /**
     * Summary of getHintForValue
     * @param mixed $key
     * @return string|null
     */
    public function findValue($key = null) : ?string;

    /**
     * 
     * @return bool
     */
    public function isRangeBased() : bool;
}