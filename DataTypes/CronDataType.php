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
        return (new CronExpression($cronString))->getNextRunDate($relativeToTime);
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
     * @param \DateTime $previousRunTime
     * @return bool
     */
    public static function isDue(string $cronString, \DateTime $previousRunTime) : bool
    {
        $shouldRun = self::findNextRunTime($cronString, $previousRunTime);
        return $shouldRun <= (new \DateTime());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::getValidationDescription()
     */
    protected function getValidationDescription() : string
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        return $translator->translate('DATATYPE.VALIDATION.MUST') . ' ' . $translator->translate('DATATYPE.VALIDATION.CRON') . '.';
    }
}