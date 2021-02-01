<?php
namespace exface\Core\DataTypes;

use Cron\CronExpression;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

/**
 * Data type for CRON expressions.
 * 
 * @author Andrej Kabachnik
 *
 */
class CronDataType extends StringDataType
{
    /**
     * 
     * @param string $string
     * @throws DataTypeCastingError
     * @return string
     */
    public static function cast($string)
    {
        if (static::isValueEmpty($string)) {
            return $string;
        }
        
        try {
            new CronExpression($string);
        } catch (\InvalidArgumentException $e) {
            throw new DataTypeCastingError($e->getMessage(), null, $e);
        }
        
        return $string;
    }
    
    /**
     * 
     * @param string $cronString
     * @return \DateTime
     */
    public static function findNextRunTime(string $cronString, \DateTime $relativeToTime) : \DateTime
    {
        return (new CronExpression($cronString))->getNextRunDate($relativeToTime, 0, true);
    }
    
    /**
     * 
     * @param string $cronString
     * @return \DateTime
     */
    public static function findPreviousRunTime(string $cronString, \DateTime $relativeToTime) : \DateTime
    {
        return (new CronExpression($cronString))->getPreviousRunDate($relativeToTime, 0, true);
    }
    
    /**
     * 
     * @param string $cronString
     * @param \DateTime $relativeToTime
     * @return bool
     */
    public static function isDue(string $cronString, \DateTime $relativeToTime) : bool
    {
        $shouldRun = self::findNextRunTime($cronString, $relativeToTime);
        return $shouldRun <= $relativeToTime;
    }
}