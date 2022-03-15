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
}