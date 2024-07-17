<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateTimeDataType;

/**
 * Flattens a given date to a specified interval, e.g.
 * get the first day of a month or a specific time on that day.
 *
 * **NOTE:** this formula always returns a date-time. Even if the input was a pure date!
 *
 * Supported intervals:
 *
 * - `Y` - years
 * - `M` - months
 * - 'W' - day of the week
 * - `D` - days (default)
 * - `h` - hours
 * - `m` - minutes
 *
 * Examples:
 *
 * - `=DateAdd('2022-10-21 18:45:13' 'M', -1)` -> 2022-10-01 00:00:00
 * - `=DateAdd('2022-10-21 18:45:13' 'W', 5)` -> 2022-10-01 00:00:00
 * - `=Date(DateAdd('2022-10-21 18:45:13', 'h', -1))` -> 2022-10-21 18:00:00
 * - `=DateAdd('2022-10-21 18:45:13')` -> 2022-10-21 00:00:00
 * - `=DateAdd('2024-02-21 18:45:13', 30, 'M')` -> 2024-02-29 00:00:00
 *
 * @author Georg Bieger
 *
 */
class DateFlatten extends Formula
{
    // Static interval index for parsed dates.
    const INTERVAL_INDEX = [
        'm' => 'second',
        'h' => 'minute',
        'D' => 'hour',
        'W' => 'day',
        'M' => 'day',
        'Y' => 'month',
    ];

    /**
     *
     * @param null $date
     * @param string $interval
     * @param int $startValue
     * @return \DateTimeInterface
     */
    public function run($date = null, string $interval = 'D', int $startValue = 0) : \DateTimeInterface
    {
        if ($date === null || $date === '') {
            throw new UnexpectedValueException('Invalid date/time "' . $date . '" provided!');
        }

        $parsed = date_parse(DateTimeDataType::cast($date));
        if($parsed['error_count'] > 0) {
            throw new UnexpectedValueException('Invalid date/time "' . $date . '" provided!');
        }

        if($interval == 's') {
            return DateTimeDataType::cast($date);
        }
        $startValue = $this->clampDateDigit($startValue, $interval, $parsed['year'], $parsed['month']);

        // Loop through date string, flattening anything below the desired cutoff.
        foreach (self::INTERVAL_INDEX as $indexedPeriod => $periodKey) {
            // Current index is at the desired cutoff.
            if($interval == $indexedPeriod) {
                // Apply $startValue and return result.
                $parsed[$periodKey] = $interval == 'W' ?
                    $parsed['day'] - ($parsed['day'] % 7) + $startValue :
                    $startValue;

                return $this->composeDate($parsed);
            }

            // Flatten at current index.
            $parsed[$periodKey] = $periodKey == 'day' || $periodKey == 'month' ? 1 : 0;
        }

        // If we did not return by now, $interval must have been invalid.
        throw new UnexpectedValueException('Invalid period "' . $interval . '" provided! Period must be one of the following: \'Y\', \'M\', \'D\', \'h\' or \'m\'.');
    }

    /**
     * Clamp a date-digit to ensure its bounds.
     *
     * @param int $value
     * @param string $interval
     * @param int $year
     * @param int $month
     * @return int|mixed
     */
    private  function clampDateDigit(int $value, string $interval, int $year, int $month) : mixed
    {
        switch (self::INTERVAL_INDEX[$interval]){
            case 'second':
            case 'minute':
                $value = min( max ($value, 0 ), 59 ); break;
            case 'hour':
                $value = min( max ($value, 0 ), 23 ); break;
            case 'day':
                if($interval == 'W') {
                    $value = min( max ($value, 1 ), 7 );
                } else {
                    $value = min( max ($value, 1 ), cal_days_in_month(CAL_GREGORIAN, $month,$year) );
                }
                break;
            case 'month':
                $value = min( max ($value, 1 ), 12 ); break;
            case 'year':
                $value = max ($value, 0 ); break;
        }

        return $value;
    }

    private function FlattenToWeek($parsed, int $week){

    }

    /**
     * Composes a new Date-Time object from parsed data.
     * @param $parsed
     * @return bool|\DateTime|\DateTimeInterface|string|NULL
     */
    private function composeDate($parsed) : \DateTimeInterface
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
    public function getDataType(): \exface\Core\Interfaces\DataTypes\DataTypeInterface
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), DateTimeDataType::class);
    }
}