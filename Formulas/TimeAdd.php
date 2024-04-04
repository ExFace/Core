<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\TimeDataType;

/**
 * Adds a positive or negative interval to a time value.
 *
 * Supported intervals:
 *
 * - `h` - hours
 * - `m` - minutes
 * - `s` - seconds
 *
 * Examples:
 *
 * - `=TimeAdd('13:45:00', 1, 'h')` -> 2022-10-21 14:45:00
 * - `=TimeAdd('13:45:00', -5, 'm')` -> 2022-10-21 13:40:00
 * 
 * @author Andrej Kabachnik
 */
class TimeAdd extends Formula
{
    /**
     * 
     * @param string $dateTimeString
     * @param int $number
     * @param string $period
     * @return string
     */
    public function run($dateTimeString = null, int $number, string $period = 'D')
    {
        return TimeDataType::add($dateTimeString, $number, $period);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), TimeDataType::class);
    }
}