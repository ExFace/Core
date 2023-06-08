<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

/**
 * Data type for message codes in the metamodel.
 * 
 * @author Andrej Kabachnik
 *
 */
class MessageCodeDataType extends StringDataType
{
    const VALIDATION_REGEX = '/^[0-9A-Z-]+$/';
    
    /**
     * 
     * @param string|null $string
     * @throws DataTypeCastingError
     * @return string|null
     */
    public static function cast($string)
    {
        if (MessageCodeDataType::isValueEmpty($string) === true){
            return $string;
        } 
        
        if (preg_match(self::VALIDATION_REGEX, $string) !== 1) {
            throw new DataTypeCastingError('Invalid message code "' . $string . '"!');
        }
        
        return $string;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::getValidatorRegex()
     */
    public function getValidatorRegex() : ?string
    {
        return parent::getValidatorRegex() ?? self::VALIDATION_REGEX;
    }
}