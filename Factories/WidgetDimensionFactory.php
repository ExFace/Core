<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\WidgetDimension;

abstract class WidgetDimensionFactory extends AbstractStaticFactory
{

    /**
     * Creates an empty dimension object
     *
     * @param exface $exface            
     * @return WidgetDimension
     */
    public static function createEmpty(Workbench $exface)
    {
        return new WidgetDimension($exface);
    }

    /**
     * Parses a dimension string (e.g.
     * 100% or 68px) into a dimension object
     *
     * @param exface $exface            
     * @param string $string            
     * @return WidgetDimension
     */
    public static function createFromString(Workbench $exface, $string)
    {
        $d = new WidgetDimension($exface, $string);
        return $d;
    }

    /**
     *
     * @param Workbench $exface            
     * @param string|WidgetDimension $string_or_dimension            
     * @return \exface\Core\CommonLogic\WidgetDimension
     */
    public static function createFromAnything(Workbench $exface, $string_or_dimension)
    {
        if ($string_or_dimension instanceof WidgetDimension) {
            return $string_or_dimension;
        } else {
            return static::createFromString($exface, $string_or_dimension);
        }
    }
}
?>