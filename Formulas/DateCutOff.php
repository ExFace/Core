<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateTimeDataType;

/**
 *
 *
 * **NOTE:** this formula always returns a date-time. Even if the input was a pure date!
 *
 * Supported intervals:
 *
 * - `Y` - years,
 * - `M` - months
 * - `W` - weeks
 * - `D` - days (default)
 * - `h` - hours
 * - `m` - minutes
 * - `s` - seconds
 *
 * Examples:
 *
 * - `=DateAdd('2022-10-21', -1)` -> 2022-10-20 00:00:00
 * - `=Date(DateAdd('2022-10-21', -1))` -> 2022-10-20
 * - `=DateAdd('2022-10-21', 1, 'W')` -> 2022-10-28 00:00:00
 * - `=DateAdd('2022-10-21 13:45:00', 1, 'h')` -> 2022-10-21 14:45:00
 *
 * @author Andrej Kabachnik
 *
 */
class DateCutOff extends Formula
{
    static $index = [
        'm' => 'second',
        'h' => 'minute',
        'D' => 'hour',
        'M' => 'day',
        'Y' => 'month',
        /*'Y' => 'year'*/];

    /**
     *
     * @param string $dateTimeString
     * @param int $number
     * @param string $period
     * @return string
     */
    public function run($baseDate = null, string $period = 'D', int $startValue = 0)
    {
        if ($baseDate === null || $baseDate === '') {
            throw new UnexpectedValueException('Invalid date/time "' . $baseDate . '" provided!');
        }

        $parsed = date_parse(DateTimeDataType::cast($baseDate));
        if($parsed['error_count'] > 0)
            throw new UnexpectedValueException('Invalid date/time "' . $baseDate . '" provided!');

        if($period == 's')
            return DateTimeDataType::cast($baseDate);

        $startValue = $this->ClampDate($startValue, $period, $parsed['year'], $parsed['month']);

        foreach (static::$index as $indexedPeriod => $periodKey){
            if($period == $indexedPeriod){
                $parsed[$periodKey] = max( abs($startValue), $periodKey == 'day' || $periodKey == 'month' ? 1 : 0);
                return $this->ComposeDate($parsed);
            }

            $parsed[$periodKey] = $periodKey == 'day' || $periodKey == 'month' ? 1 : 0;
        }

        throw new UnexpectedValueException('Invalid period "' . $period . '" provided! Period must be one of the following: \'Y\', \'M\', \'D\', \'h\' or \'m\'.');
    }

    private  function ClampDate(int $value, string $period, int $year, int $month){
        switch (static::$index[$period]){
            case 'second':
            case 'minute':
                $value = min( max ($value, 0 ), 59 ); break;
            case 'hour':
                $value = min( max ($value, 0 ), 23 ); break;
            case 'day':
                $value = min( max ($value, 1 ), cal_days_in_month(CAL_GREGORIAN, $month,$year) ); break;
            case 'month':
                $value = min( max ($value, 1 ), 12 ); break;
            case 'year':
                $value = max ($value, 0 ); break;
        }

        return $value;
    }

    private function ComposeDate($parsed)
    {
        $result =
            $parsed['year'].'-'.
            $parsed['month'].'-'.
            $parsed['day'].' '.
            $parsed['hour'].':'.
            $parsed['minute'].':'.
            $parsed['second'];

        return DateTimeDataType::cast($result);
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