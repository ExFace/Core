<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\DateDataType;
use exface\Core\Factories\DataTypeFactory;

/**
 * Returns the current date with the option to define a specific format.
 *
 * Similar to `=NOW()` but returns a date without a time by default.
 * Accepts a single optional perameter - the ICU date/time format.
 *
 * Examples:
 *
 * - `=TODAY()` = 2020-03-25 21:00:55
 * - `=TODAY('yyyy-MM-dd')` = 2020-03-25
 * - `=TODAY('dd.MM.yyyy')` = 25.03.2020
 *
 * See http://userguide.icu-project.org/formatparse/datetime for a complete guide to
 * the ICU date format syntax.
 *
 * @link http://userguide.icu-project.org/formatparse/datetime
 */
class Today extends Now
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Formulas\Now::getFormatDefault()
     */
    protected function getFormatDefault() : string
    {
        return DateDataType::DATE_ICU_FORMAT_INTERNAL;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Formulas\Now::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), DateDataType::class);
    }
}