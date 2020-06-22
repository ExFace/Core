<?php
namespace exface\Core\Formulas;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateDataType;

/**
 * Parses (almost) any value into a date in the internal format or a given ICU format.
 * 
 * The first parameter is the value to parse, while the second (optional) parameter is
 * the ICU date format.
 * 
 * Examples:
 * 
 * - `=DATE('25.03.2020')` = 2020-03-25
 * - `=DATE('1585090800')` = 2020-03-25
 * - `=DATE('1585090800', 'dd.MM.yyyy)` = 25.03.2020
 * - `=DATE('2020-03-25', 'yyyyMMddHHmmss')` = 20200325000000
 * 
 * See http://userguide.icu-project.org/formatparse/datetime for a complete guide to
 * the ICU date format syntax.
 *
 * @link http://userguide.icu-project.org/formatparse/datetime
 */
class Date extends \exface\Core\CommonLogic\Model\Formula
{

    function run($date, $format = '')
    {
        if (! $date)
            return;
        return $this->formatDate($date, $format);
    }

    function formatDate($date, $format = '')
    {
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
        
        $dataType = $this->getDataType();
        if ($format) {
            $dataType->setFormat($format);
        } else {
            $dataType->setFormat(DateDataType::DATE_ICU_FORMAT_INTERNAL);
        }
        
        return $dataType->formatDate($date);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), DateDataType::class);
    }
}