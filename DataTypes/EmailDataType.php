<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

/**
 * Data type for email addresses.
 * 
 * @author Andrej Kabachnik
 *
 */
class EmailDataType extends StringDataType
{
    /**
     * 
     * @param mixed $string
     * @throws DataTypeCastingError
     * @return string
     */
    public static function cast($string)
    {
        if (static::isValueEmpty($string) === true){
            return $string;
        } 
        
        if (false === $filtered = filter_var($string, FILTER_VALIDATE_EMAIL)) {
            throw new DataTypeCastingError('Invalid email "' . $string . '"!');
        }
        
        return $filtered;
    }
    
    /**
     * 
     * @param mixed $string
     * @return bool
     */
    public static function isValueEmail($string) : bool
    {
        if ($string === '' || $string === null) {
            return false;
        }
        return false !== filter_var($string, FILTER_VALIDATE_EMAIL);
    }
}