<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\FormulaError;
use exface\Core\DataTypes\IntegerDataType;

/**
 * Calculates the number of days, months, or years between two dates.
 * 
 * Examples:
 * 
 * - `=TimeDif('10:00', '13:00', 'H')` = 3
 * - `=TimeDif('10:00', '13:00', 'M')` = 180
 * - `=TimeDif('10:00', '13:00', 'S')` = 10800
 * - `=TimeDif('23:30', '03:00', 'S')` = 12600
 * 
 */
class TimeDif extends \exface\Core\CommonLogic\Model\Formula
{
    const UNIT_H = 'H';
    const UNIT_M = 'M';
    const UNIT_S = 'S';
    
    /**
     * 
     * @param string $date
     * @param string $format
     * @return null|int
     */
    public function run($start_date = null, $end_date = null, $unit = self::UNIT_H)
    {
        if ($start_date === null || $start_date === '' || $end_date === null || $end_date === '') {
            return null;
        }
        
        $date1 = DateTimeDataType::castToPhpDate($start_date);
        $date2 = DateTimeDataType::castToPhpDate($end_date);
        if ($date1 > $date2) {
            $date2->modify('+1 days');
        }
        $interval = $date1->diff($date2);
        //throw new FormulaError("Inteval" . $interval->d . '-' . $interval->h . '-' . $interval->i . '-' . $interval->s);
        
        //$diff = 0;
        switch (mb_strtoupper($unit)) {
            case self::UNIT_H: $diff = $diff = $interval->days*24 + $interval->h; break;
            case self::UNIT_M: $diff = $interval->days*24*60 + $interval->h*60 + $interval->i; break;
            case self::UNIT_S: $diff = $interval->days*24*3600 + $interval->h*3600 + $interval->i*60 + $interval->s; break;
            default:
                throw new FormulaError('Cannot evaluate formula "' . $this->__toString() . '": invalid unit "' . $unit . '" provided!');
        }
        
        if ($date1 > $date2) {
            $diff = $diff * (-1);
        }
        return $diff;
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