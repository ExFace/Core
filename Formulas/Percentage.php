<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\PercentDataType;

/**
 * Displays a value in percent of another one.
 * 
 * - `=Percentage(250, 1000)` -> 25
 * - `=Percentage(5, 6)` -> 83,3
 * - `=Percentage(5, 6, 0)` -> 83
 * - `=Percentage(attribute_alias_1, attribute_alias_2)`
 *
 * @author Andrej Kabachnik
 *        
 */
class Percentage extends Formula
{

    function run($value, $in_percent_of, $precision = 1)
    {
        if (! $in_percent_of)
            return 0;
        return round(($value / $in_percent_of) * 100, $precision);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), PercentDataType::class);
    }
}