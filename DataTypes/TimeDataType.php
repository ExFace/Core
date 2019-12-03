<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

class TimeDataType extends AbstractDataType
{    
    private $showSeconds = false;
    
    private $amPm = false;
    
    const TIME_FORMAT_INTERNAL = 'H:i:s';
    
    const TIME_ICU_FORMAT_INTERNAL = 'HH:mm:ss';
    
    public static function cast($string)
    {
        $string = trim($string);
        
        if ($string === '' || $string === null) {
            return $string;
        }
        
        /*
        // Return NULL for casting empty values as an empty string '' actually is not a date!
        if (static::isEmptyValue($string) === true) {
            return null;
        }
        
        if ($relative = static::parseRelativeDate($string)){
            return $relative;
        }
        
        if ($short = static::parseShortDate($string)){
            return $short;
        }*/
        
        try {
            $date = new \DateTime($string);
        } catch (\Exception $e) {
            // FIXME add message code with descriptions of valid formats
            throw new DataTypeCastingError('Cannot convert "' . $string . '" to a time!', '74BKHZL', $e);
        }
        return static::formatTimeNormalized($date);
    }
    
    public function parse($value)
    {
        try {
            return parent::parse($value);
        } catch (DataTypeValidationError | DataTypeCastingError $e) {
            $parsed =  $this->getIntlDateFormatter()->parse($value);
            if ($parsed === false) {
                throw $e;
            }
            return $parsed;
        }
    }
    
    protected static function createIntlDateFormatter(string $locale, string $format) : \IntlDateFormatter
    {
        return new \IntlDateFormatter($locale, NULL, NULL, NULL, NULL, $format);
    }
    
    protected function getIntlDateFormatter() : \IntlDateFormatter
    {
        return self::createIntlDateFormatter($this->getLocale(), $this->getFormat());
    }
    
    protected function getLocale() : string
    {
        return $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
    }
    /*
    public static function parseShortDate($string)
    {
        $matches = [];
        if (strlen($string) == 4 && is_int($string)){
            return static::formatDateNormalized(new \DateTime($string . '-01-01'));
        } elseif (preg_match('/^([0-9]{1,2})[\.-]([0-9]{4})$/', $string, $matches)){
            return $matches[2] . '-' . $matches[1] . '-01';
        } elseif (preg_match('/^([0-9]{1,2})[\.-]([0-9]{1,2})[\.-]?$/', $string, $matches)){
            return date("Y") . '-' . $matches[2] . '-' . $matches[1];
        } else {
            return false;
        }
    }
    
    public static function parseRelativeDate($string)
    {
        $day_period = 'D';
        $week_period = 'W';
        $month_period = 'M';
        $year_period = 'Y';
        
        $matches = [];
        if (preg_match('/^([\+-]?[0-9]+)([dDmMwWyY]?)$/', $string, $matches)){
            $period = $matches[2];
            $quantifier = intval($matches[1]);
            $interval_spec = 'P' . abs($quantifier);
            switch (strtoupper($period)){
                case $day_period:
                case '':
                    $interval_spec .= 'D';
                    break;
                case $week_period:
                    $interval_spec .= 'W';
                    break;
                case $month_period:
                    $interval_spec .= 'M';
                    break;
                case $year_period:
                    $interval_spec .= 'Y';
                    break;
                default:
                    throw new UnexpectedValueException('Invalid period "' . $period . '" used in relative date "' . $quantifier . $period . '"!', '6W25AB1');
            }
            $date = new \DateTime();
            $interval = new \DateInterval($interval_spec);
            if ($quantifier > 0){
                $date->add($interval);
            } else {
                $date->sub($interval);
            }
            return static::formatDateNormalized($date);
        }
        return false;
    }
    */
    
    public static function formatTimeNormalized(\DateTime $date)
    {    
        return $date->format(self::TIME_FORMAT_INTERNAL);
    }
    
    public function formatTime(\DateTime $date) : string
    {
        return $this->getIntlDateFormatter()->format($date);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirectionsDataType::DESC($this->getWorkbench());
    }
    
    public function getFormatToParseTo() : string
    {
        return self::TIME_ICU_FORMAT_INTERNAL;
    }
    
    public function getFormat() : string
    {
        return 'HH:mm';
    }
    
    /**
     *
     * @return bool
     */
    public function getShowSeconds() : bool
    {
        return $this->showSeconds;
    }
    
    /**
     * Set to TRUE to show the seconds.
     * 
     * @uxon-property show_seconds
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return TimeDataType
     */
    public function setShowSeconds(bool $value) : TimeDataType
    {
        $this->showSeconds = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getAmPm() : bool
    {
        return $this->amPm;
    }
    
    /**
     * Set to TRUE to use the 12-h format with AM/PM.
     * 
     * @uxon-property am_pm
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return TimeDataType
     */
    public function setAmPm(bool $value) : TimeDataType
    {
        $this->amPm = $value;
        return $this;
    }
    
}