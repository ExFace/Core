<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypeValidationError;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\CommonLogic\Constants\SortingDirections;

class DateDataType extends AbstractDataType
{

    public static function parse($string)
    {
        $string = trim($string);
        
        if ($relative = static::parseRelativeDate($string)){
            return $relative;
        }
        
        if ($short = static::parseShortDate($string)){
            return $short;
        }
        
        try {
            $date = new \DateTime($string);
        } catch (\Exception $e) {
            throw new DataTypeValidationError('Cannot convert "' . $string . '" to a date!', '6W25AB1', $e);
        }
        return static::formatDate($date);
    }
    
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
        if (preg_match('/^([\+-]?[0-9]+)([dDmMwWyY])$/', $string, $matches)){
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
    
    public static function formatDate(\DateTime $date){
        return $date->format('Y-m-d');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\AbstractDataType::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirections::DESC();
    }
}
?>