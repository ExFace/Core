<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

class TimeDataType extends AbstractDataType
{    
    private $showSeconds = false;
    
    private $amPm = false;
    
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
        return static::formatTime($date);
    }
    /*
    public static function parseShortDate($string)
    {
        $matches = [];
        if (strlen($string) == 4 && is_int($string)){
            return static::formatDate(new \DateTime($string . '-01-01'));
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
            return static::formatDate($date);
        }
        return false;
    }
    */
    
    public static function formatTime(\DateTime $date)
    {    
        return $date->format('H:i:s');
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