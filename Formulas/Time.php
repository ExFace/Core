<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

/**
 * Parses (almost) any value into a date and time in the internal format or a given ICU format.
 *
 * The first parameter is the value to parse, while the second (optional) parameter is
 * the ICU date format.
 *
 * Examples:
 *
 * - `=Time('25.03.2020 21:00:55')` = 21:00:55
 * - `=Time('1585090800')` = 00:00:00
 * - `=Time('1585090800', 'HH:mm:ss)` = 00:00:00
 * - `=Time('2020-03-25 21:00:55', 'HHmmss')` = 210055
 * 
 * See http://userguide.icu-project.org/formatparse/datetime for a complete guide to
 * the ICU date format syntax.
 *
 * @link http://userguide.icu-project.org/formatparse/datetime
 */
class Time extends Date
{
    /**
     * 
     * @param string $date
     * @param string $format
     */
    public function run($date, $formatTo = TimeDataType::TIME_ICU_FORMAT_INTERNAL)
    {
        if (! $date) {
            return null;
        }
        
        $dataType = DataTypeFactory::createFromString($this->getWorkbench(), DateTimeDataType::class);
        $dataType->setFormat($formatTo);
        
        try {
            return $dataType->formatDate($dataType::castToPhpDate($date));
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