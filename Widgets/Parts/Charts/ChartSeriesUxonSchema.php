<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Uxon\UxonSchema;
use exface\Core\Widgets\Chart;

/**
 * UXON-schema class for chart widget parts.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class ChartSeriesUxonSchema extends UxonSchema
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPrototypeClass()
     */
    public function getPrototypeClass(UxonObject $uxon, array $path, string $rootPrototypeClass = '\exface\Core\Widgets\AbstractWidget') : string
    {
        $name = $rootPrototypeClass;
        
        foreach ($uxon as $key => $value) {
            if (strcasecmp($key, 'type') === 0) {
                $part = Chart::getSeriesClassName($value);
                if ($this->validatePrototypeClass($part) === true) {
                    $name = $part;
                }
                break;
            }
        }
        
        if (count($path) > 1) {
            return parent::getPrototypeClass($uxon, $path, $name);
        }
        
        return $name;
    }
}