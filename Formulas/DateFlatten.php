<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateTimeDataType;

/**
 * Flattens a given date to a specified interval, e.g.
 * get the first day of a month or a specific time on that day.
 * Dates will NOT roll over: If you select a start value that exceeds
 * the bounds of your chosen interval, the start value will be clamped.
 *
 * **NOTE:** this formula always returns a date-time. Even if the input was a pure date!
 *
 * Supported intervals:
 *
 * - `Y` - month of current year
 * - `M` - day of current month
 * - `W` - day of current week
 * - `D` - hour of current day
 * - `h` - minute of current hour
 * - `m` - second of current minute
 *
 * Examples:
 *
 * - `=DateFlatten('2022-10-21 18:45:13' 'M', -1)` -> 2022-10-01 00:00:00
 * - `=DateFlatten('2022-10-21 18:45:13' 'W', 5)` -> 2022-10-19 00:00:00
 * - `=DateFlatten('2022-10-21 18:45:13', 'h', -1)` -> 2022-10-21 18:00:00
 * - `=DateFlatten('2022-10-21 18:45:13')` -> 2022-10-21 00:00:00
 * - `=DateFlatten('2024-02-21 18:45:13', 30, 'M')` -> 2024-02-29 00:00:00
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
     * @return \DateTimeInterface | string
     */
    public function run($date = null, string $interval = 'D', int $startValue = 0) : \DateTimeInterface | string
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

        // Calculate offset by week, if selected.
        if($interval == 'W') {
            $weekDay = min( max ($startValue - 1, 0 ), 6 );
            $startValue = $parsed['day'] - (($parsed['day'] - 1) % 7) + $weekDay;
        }

        // Clamp date to ensure bounds.
        $startValue = $this->clampDateDigit(
            $startValue,
            self::INTERVAL_INDEX[$interval],
            $parsed['year'],
            $parsed['month']
        );

        // Loop through date string, flattening anything below the desired cutoff.
        foreach (self::INTERVAL_INDEX as $indexedPeriod => $periodKey) {
            // Current index is at the desired cutoff.
            if($interval == $indexedPeriod) {
                // Apply $startValue and return result.
                $parsed[$periodKey] = $startValue;
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
        switch ($interval){
            case 'second':
            case 'minute':
                $value = min( max ($value, 0 ), 59 ); break;
            case 'hour':
                $value = min( max ($value, 0 ), 23 ); break;
            case 'day':
                $value = min( max ($value, 1 ), cal_days_in_month(CAL_GREGORIAN, $month,$year) );
                break;
            case 'month':
                $value = min( max ($value, 1 ), 12 ); break;
            case 'year':
                $value = max ($value, 0 ); break;
        }

        return $value;
    }

    /**
     * Composes a new Date-Time object from parsed data.
     * @param $parsed
     * @return \DateTimeInterface | string
     */
    private function composeDate($parsed) : \DateTimeInterface | string
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