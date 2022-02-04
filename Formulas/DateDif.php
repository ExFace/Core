<?php
namespace exface\Core\Formulas;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Exceptions\FormulaError;
use exface\Core\DataTypes\IntegerDataType;

/**
 * Calculates the number of days, months, or years between two dates.
 * 
 * Examples:
 * 
 * - `=DateDif('01.01.2014', '06.05.2016')` = 856
 * - `=DateDif('01.01.2014', '06.05.2016', 'D')` = 856
 * - `=DateDif('01.01.2014', '06.05.2016', 'M')` = 28
 * - `=DateDif('01.01.2014', '06.05.2016', 'Y')` = 2
 * 
 */
class DateDif extends \exface\Core\CommonLogic\Model\Formula
{
    const UNIT_D = 'D';
    const UNIT_M = 'M';
    const UNIT_Y = 'Y';
    
    /**
     * 
     * @param string $date
     * @param string $format
     * @return void|\DateTime
     */
    public function run($start_date = null, $end_date = null, $unit = self::UNIT_D)
    {
        if ($start_date === null || $start_date === '' || $end_date === null || $end_date === '') {
            return null;
        }
        
        $date1 = DateDataType::castToPhpDate($start_date);
        $date2 = DateDataType::castToPhpDate($end_date);
        $interval = $date1->diff($date2);
        
        switch (mb_strtoupper($unit)) {
            case self::UNIT_D: return $interval->days;
            case self::UNIT_M: return $interval->y * 12 + $interval->m;
            case self::UNIT_Y: return $interval->y;
            default:
                throw new FormulaError('Cannot evaluate formula "' . $this->__toString() . '": invalid unit "' . $unit . '" provided!');
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), IntegerDataType::class);
    }
}