<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateTimeDataType;

/**
 * Adds a positive or negative interval to a date/time value.
 * 
 * Supported intervals:
 * 
 * - `Y` - years, 
 * - `M` - months
 * - `W` - weeks
 * - `D` - days (default)
 * - `h` - hours
 * - `m` - minutes 
 * - `s` - seconds
 * 
 * Examples:
 * 
 * - `=DateAdd('2022-10-21', -1, 'D')` -> 2022-10-20
 * - `=DateAdd('2022-10-21', 1, 'W')` -> 2022-10-28
 * - `=DateAdd('2022-10-21 13:45:00', 1, 'h')` -> 2022-10-21 14:45:00
 * 
 * @author Andrej Kabachnik
 *
 */
class DateAdd extends Formula
{
    /**
     *
     * @param string $dateTimeString
     * @param int $number
     * @param string $period
     * @return string
     */
    public function run($dateTimeString = null, int $number = 0, string $period = 'D')
    {
        return DateTimeDataType::addInterval($dateTimeString, $number, $period);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), DateTimeDataType::class);
    }
}