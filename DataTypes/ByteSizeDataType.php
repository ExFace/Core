<?php
namespace exface\Core\DataTypes;

/**
 * Special data type for bytes, KB, MB, GB, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
class ByteSizeDataType extends NumberDataType
{
    const SCALE_TYPE_M = 1;
    
    const SCALE_TYPE_MB = 2;
    
    /**
     * 
     */
    protected function init()
    {
        // No decimals by default
        $this->setPrecisionMax(0);
    }
    
    /**
     * 
     * @param int|float|string $string
     * @return string|number
     */
    public static function cast($string)
    {
        $string  = trim($string);
        
        if (is_numeric($string)) {
            return $string;
        }
            
        $last = strtolower($string[strlen($string)-1]);
        $multiplier = 1;
        switch($last) {
            // If it's KB, MB, etc. - remove the B and try again
            case 'b':
                return self::cast(substr($string, 0, -1));
            case 'y':
                $multiplier *= 1024;
            case 'z':
                $multiplier *= 1024;
            case 'e':
                $multiplier *= 1024;
            case 'p':
                $multiplier *= 1024;
            case 'T':
                $multiplier *= 1024;
            case 'g':
                $multiplier *= 1024;
            case 'm':
                $multiplier *= 1024;
            case 'k':
                $multiplier *= 1024;
        }
        
        if ($multiplier > 1) {
            $string = substr($string, 0, -1);
            $number = parent::cast($string);
            $number *= $multiplier;
        }
        
        return $number;
    }
    
    /**
     * 
     * @param int|float $number
     * @param int $decimals
     * @param int $scaleType
     * @return string
     */
    public static function formatWithScale($number, int $decimals = null, int $scaleType = self::SCALE_TYPE_MB) : string
    {
        $number = parent::cast($number);
                
        $k = 1024;
        $sizes = [
            self::SCALE_TYPE_MB => ['', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'],
            self::SCALE_TYPE_M => ['', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y']
        ];
        $i = floor(log($number) / log($k));
        $numberScaled = ($number / pow($k, $i));
        if ($decimals === null) {
            $decimals = $numberScaled < 10 && floor($numberScaled) != $numberScaled ? 1 : 0;
        }
        
        return round($numberScaled, $decimals) . ' ' . $sizes[$scaleType][$i];
    }
}