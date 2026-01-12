<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Factories\DataTypeFactory;

/**
 * Returns the current date or date and time with the option to define a specific format.
 *
 * Accepts a single optional perameter - the ICU date/time format.
 *
 * Examples:
 *
 * - `=Now()` = 2020-03-25 21:00:55
 * - `=Now('yyyy-MM-dd')` = 2020-03-25
 * - `=Now('dd.MM.yyyy')` = 25.03.2020
 * - `=Now('dd.MM.yyyy HH:mm:ss')` = 25.03.2020 21:00:55
 *
 * See https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax for a complete guide to
 * the ICU date format syntax.
 *
 * @link https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax
 */
class Now extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * @param string $format
     * @return string
     */
    public function run($format = '')
    {
        if ($format === 'Y-m-d') {
            $format = 'yyyy-MM-dd';
        } elseif ($format === 'Y-m-d H:i:s') {
            $format = 'yyyy-MM-dd HH:mm:ss';
        }
        
        $date = new \DateTime();
        
        $dataType = DataTypeFactory::createFromPrototype($this->getWorkbench(), DateTimeDataType::class);
        if ($format) {
            $dataType->setFormat($format);
        } else {
            $dataType->setFormat($this->getFormatDefault());
        }
        
        return $dataType->formatDate($date);
    }
    
    /**
     * 
     * @return string
     */
    protected function getFormatDefault() : string
    {
        return DateTimeDataType::DATETIME_ICU_FORMAT_INTERNAL;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        $type = DataTypeFactory::createFromPrototype($this->getWorkbench(), DateTimeDataType::class);
        // TODO if a custom format was given, pass it to the data type too!
        // Otherwise, reading =Now('dd.MM.yyyy') in a data sheet will produce a date column, that still has the default
        // format making widgets and template renderers reformat the value back to the default yyyy-MM-dd notation. 
        return $type;
    }
}