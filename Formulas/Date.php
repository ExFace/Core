<?php
namespace exface\Core\Formulas;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

/**
 * Parses (almost) any value into a date in the internal format or a given ICU format.
 * 
 * The first parameter is the value to parse, while the second (optional) parameter is
 * the ICU date format.
 * 
 * Examples:
 * 
 * - `=Date('25.03.2020 21:00:55')` = 2020-03-25
 * - `=Date('1585090800')` = 2020-03-25
 * - `=Date('1585090800', 'dd.MM.yyyy)` = 25.03.2020
 * - `=Date('2020-03-25', 'yyyyMMddHHmmss')` = 20200325000000
 * - `=Date('2021-07-08', 'E')` = Thu
 * 
 * See http://userguide.icu-project.org/formatparse/datetime for a complete guide to
 * the ICU date format syntax.
 *
 * @link http://userguide.icu-project.org/formatparse/datetime
 */
class Date extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * @param string $date
     * @param string $format
     * @return void|\DateTime
     */
    public function run($date, $format = DateDataType::DATE_ICU_FORMAT_INTERNAL)
    {
        if (! $date) {
            return null;
        }
        return $this->formatDate($date, $format);
    }

    /**
     * 
     * @param mixed $date
     * @param string $formatTo
     * @return string
     */
    protected function formatDate($date, string $formatTo) : string
    {
        $formatTo = $this->sanitizeFormat($formatTo);
        
        $dataType = $this->getDataType();
        $dataType->setFormat($formatTo);
        
        try {
            return $dataType->formatDate($dataType::castToPhpDate($date));
        } catch (DataTypeCastingError $e) {
            return $date;
        }
    }
    
    /**
     * 
     * @param string $formatArgument
     * @return string|NULL
     */
    protected function sanitizeFormat(string $formatArgument) : ?string
    {
        $format = $formatArgument;
        if ($format === 'Y-m-d') {
            $format = 'yyyy-MM-dd';
        } elseif ($format === 'Y-m-d H:i:s') {
            $format = 'yyyy-MM-dd HH:mm:ss';
        }
        return $format;
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