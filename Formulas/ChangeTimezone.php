<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\TimeZoneDataType;
use exface\Core\Exceptions\FormulaError;

/**
 * Converts a datetime value from one timezone to another timezone.
 * 
 * Examples:
 * 
 * - `=ChangeTimezone('2022-10-21 13:45:00', 'UTC', 'Europe/Berlin')` -> 2022-10-21 14:45:00
 * - `=ChangeTimezone('2022-10-21 13:45:00', 'UTC')` -> 2022-10-21 14:45:00 (converted to PHP server time)
 * 
 * @author Ralf Mulansky
 *
 */
class ChangeTimezone extends Formula
{
    /**
     * 
     * @param string $dateTimeString
     * @param string $fromTz
     * @param string $toTz
     * @return string
     */
    public function run($dateTimeString = null, string $fromTz = null, string $toTz = null)
    {
        $workbench = $this->getWorkbench();
        if ($fromTz === null) {
            $fromTz = DateTimeDataType::getTimeZoneDefault($workbench);
        }
        if ($toTz === null) {
            $toTz = DateTimeDataType::getTimeZoneDefault($workbench);
        }
        if (! TimeZoneDataType::isValidStaticValue($fromTz)) {
            throw new FormulaError("Can not convert value '{$dateTimeString}'. Timezone '{$fromTz}' is not a valid timezone!");
        }
        if (! TimeZoneDataType::isValidStaticValue($toTz)) {
            throw new FormulaError("Can not convert value '{$dateTimeString}'. Timezone '{$toTz}' is not a valid timezone!");
        }
        $test = DateTimeDataType::convertTimeZone($dateTimeString, $fromTz, $toTz);
        return DateTimeDataType::convertTimeZone($dateTimeString, $fromTz, $toTz);
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