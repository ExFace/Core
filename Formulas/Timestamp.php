<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\TimestampDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateTimeDataType;

/**
 * Converts a date in almost any format to seconds since 01.01.1970 00:00:00
 * 
 * Examples: 
 * 
 * - `=TIMESTAMP('2020-03-25')` = 1585090800
 * - `=TIMESTAMP()` = current timestamp
 * - `=TIMESTAMP('2020-03-25', 1000)` = 1585090800000
 * 
 * The first parameter is the date to convert. If not specified, the current
 * date/time will be used.
 * 
 * The second parameter is a multiplier - set it to `1000` to get milliseconds
 * instead of seconds. 
 * 
 * @author Andrej Kabachnik
 *
 */
class Timestamp extends \exface\Core\CommonLogic\Model\Formula
{

    /**
     *
     * @param string $date            
     * @param number $multiplier  
     *           
     * @return number
     */
    function run($date, $multiplier = 1)
    {
        if ($date === null && $date === '') {
            return strtotime() * $multiplier;
        }
        if ($date === 0) {
            return $date;
        }
        return strtotime(DateTimeDataType::cast($date)) * $multiplier;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), TimestampDataType::class);
    }
}