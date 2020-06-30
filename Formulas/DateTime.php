<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Factories\DataTypeFactory;

/**
 * Parses (almost) any value into a date and time in the internal format or a given ICU format.
 *
 * The first parameter is the value to parse, while the second (optional) parameter is
 * the ICU date format.
 *
 * Examples:
 *
 * - `=DATETIME('25.03.2020 21:00:55')` = 2020-03-25 21:00:55
 * - `=DATETIME('1585090800')` = 2020-03-25 00:00:00
 * - `=DATETIME('1585090800', 'dd.MM.yyyy HH:mm:ss)` = 25.03.2020 00:00:00
 * - `=DATETIME('2020-03-25', 'yyyyMMddHHmmss')` = 20200325210055
 * 
 * See http://userguide.icu-project.org/formatparse/datetime for a complete guide to
 * the ICU date format syntax.
 *
 * @link http://userguide.icu-project.org/formatparse/datetime
 */
class DateTime extends \exface\Core\CommonLogic\Model\Formula
{

    function run($date, $format = '')
    {
        if (! $date)
            return;
        
        if ($format === 'Y-m-d') {
            $format = 'yyyy-MM-dd';
        } elseif ($format === 'Y-m-d H:i:s') {
            $format = 'yyyy-MM-dd HH:mm:ss';
        }
        try {
            $date = new \DateTime($date);
        } catch (\Exception $e) {
            return $date;
        }
        
        
        $dataType = DataTypeFactory::createFromPrototype($this->getWorkbench(), DateTimeDataType::class);
        if ($format) {
            $dataType->setFormat($format);
        }
        
        return $dataType->formatDate($date);
    }
}
?>