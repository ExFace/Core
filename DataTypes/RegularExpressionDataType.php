<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

/**
 * Data type for regular expression patterns.
 * 
 * @author Andrej Kabachnik
 *
 */
class RegularExpressionDataType extends StringDataType
{
    const DELIMITERS = ['/', '~', '@', ';', '%', '`'];
    
    private $customDelimiters = null;
    
    private $delimitersRequired = true;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::cast()
     */
    public static function cast($string)
    {
        if (static::isValueEmpty($string)) {
            return $string;
        }
        
        if (static::isRegex($string) === false) {
            throw new DataTypeCastingError('Not a valid regular expression: "' . $string . '"');
        }
        
        return $string;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::parse()
     */
    public function parse($string)
    {
        if (static::isValueEmpty($string)) {
            return $string;
        }
        
        if ($this->getDelimitersRequired() && static::isRegex($string, $this->getDelimiters()) === false) {
            throw new DataTypeValidationError($this, 'Not a valid regular expression: "' . $string . '"');
        }
        
        return $string;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getDelimiters() : array
    {
        return $this->customDelimiters ?? self::DELIMITERS;
    }
    
    /**
     * Cusotmize the allowed regex delimiters
     * 
     * @uxon-property delimiters
     * @uxon-type array
     * @uxon-default ["\/", "~", "@", ";", "%", "`"]
     * @uxon-template ["\/", "~", "@", ";", "%", "`"]
     * 
     * @param string[] $value
     * @return RegularExpressionDataType
     */
    public function setDelimiters(array $value) : RegularExpressionDataType
    {
        $this->customDelimiters = $value;
        return $this;
    }    
    
    /**
     * Returns TRUE if the given string is enclosed in regex delimiters.
     * 
     * The allowed delimiters can be speicified explicitly via `$delimiters`, otherwise
     * the standard PHP regex delimiters will be used
     * 
     * @param string $string
     * @return bool
     */
    public static function isRegex(string $string, array $delimiters = null) : bool
    {
        return self::findDelimiter($string, $delimiters) === null ? false : true;
    }
    
    /**
     * Returns the delimiter used in this pattern or NULL if it is not a delimited regex.
     * 
     * The allowed delimiters can be speicified explicitly via `$delimiters`, otherwise
     * the standard PHP regex delimiters will be used
     * 
     * @param string $pattern
     * @param array $delimiters
     * @return string|NULL
     */
    public static function findDelimiter(string $pattern, array $delimiters = null) : ?string
    {
        $pattern = trim($pattern);
        
        // Need at least 2 charactersfor the delimiters!
        if (strlen($pattern) <= 2) {
            return null;
        }
        
        $delimiters = $delimiters ?? self::REGEX_DELIMITERS;
        foreach ($delimiters as $delim) {
            if (StringDataType::startsWith($pattern, $delim) === true && StringDataType::endsWith($pattern, $delim) === true) {
                return $delim;
            }
        }
        
        return null;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getDelimitersRequired() : bool
    {
        return $this->delimitersRequired;
    }
    
    /**
     * Set to FALSE to allow patterns not enclosed in delimiters.
     * 
     * @uxon-property delimiters_required
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return RegularExpressionDataType
     */
    public function setDelimitersRequired(bool $value) : RegularExpressionDataType
    {
        $this->delimitersRequired = $value;
        return $this;
    }
}