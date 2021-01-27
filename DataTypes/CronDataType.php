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
    public static function findNextTime(string $cronString) : \DateTime
    {
        return (new CronExpression($cronString))->getNextRunDate();
    }
    
    /**
     * 
     * @param string $cronString
     * @return \DateTime
     */
    public static function findPreviousTime(string $cronString) : \DateTime
    {
        return (new CronExpression($cronString))->getPreviousRunDate();
    }
    
    public static function isDue(string $cronString) : bool
    {
        return (new CronExpression($cronString))->isDue();
    }
}