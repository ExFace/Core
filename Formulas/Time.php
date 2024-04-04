<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\CommonLogic\Model\Formula;

/**
 * Extracts the time from (almost) any date-related value.
 *
 * The first parameter is the value to parse, while the second (optional) parameter is
 * the ICU date format. Additionally you can specify a custom input-format in ICU syntax
 * if the automatic parser does not work properly for your date.
 *
 * Examples:
 *
 * - `=Time('25.03.2020 21:00:55')` = 21:00:55
 * - `=Time('1585090800')` = 00:00:00
 * - `=Time('1585090800', 'HH:mm:ss)` = 00:00:00
 * - `=Time('2020-03-25 21:00:55', 'HHmmss')` = 210055
 * 
 * See https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax for a complete guide to
 * the ICU date format syntax.
 *
 * @link https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax
 */
class Time extends Formula
{
    /**
     * 
     * @param string $date
     * @param string $format
     */
    public function run($date = null, $formatTo = TimeDataType::TIME_ICU_FORMAT_INTERNAL, $inputFormat = null)
    {
        if ($date === null || $date === '') {
            return null;
        }
        
        $dataType = DataTypeFactory::createFromString($this->getWorkbench(), DateTimeDataType::class);
        $dataType->setFormat($formatTo);
        
        if ($inputFormat !== null) {
            $phpDate = DateTimeDataType::castFromFormat($date, $inputFormat, $dataType->getLocale(), true);
        } else {
            $phpDate = $dataType::castToPhpDate($date);
        }
        
        try {
            return $dataType->formatDate($phpDate);
        } catch (DataTypeCastingError $e) {
            return $date;
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
?>