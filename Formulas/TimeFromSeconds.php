<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\CommonLogic\Model\Formula;

/**
 * Converts a number of seconds to time (hh:mm:ss)
 *
 * Examples:
 *
 * - `=TimeFromSeconds(81.52)` = 00:01:21
 * - `=Time(81.52, 'mm:ss')` = 01:21
 * - `=Time(81.52, 'mm:ss.SS')` = 01:21.52
 * 
 * See https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax for a complete guide to
 * the ICU date format syntax.
 *
 * @link https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax
 */
class TimeFromSeconds extends Formula
{
    /**
     * @param $seconds
     * @param $formatTo
     * @return mixed|null
     */
    public function run($seconds = null, $formatTo = TimeDataType::TIME_ICU_FORMAT_INTERNAL)
    {
        if ($seconds === null || $seconds === '') {
            return null;
        }
        
        $dataType = DataTypeFactory::createFromString($this->getWorkbench(), DateTimeDataType::class);
        $dataType->setFormat($formatTo);
        
        $time = TimeDataType::convertSecondsToTime($seconds, 0);
        
        try {
            return $dataType->format($time);
        } catch (DataTypeCastingError $e) {
            return $seconds;
        }
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