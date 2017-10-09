<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\Constants\SortingDirections;
use exface\Core\Exceptions\DataTypeValidationError;

class StringDataType extends AbstractDataType
{
    private $lengthMin = 0;
    
    private $lengthMax = null;

    /**
     * Converts a string from under_score (snake_case) to camelCase.
     *
     * @param string $string            
     * @return string
     */
    public static function convertCaseUnderscoreToCamel($string)
    {
        return lcfirst(static::convertCaseUnderscoreToPascal($string));
    }

    /**
     * Converts a string from camelCase to under_score (snake_case).
     *
     * @param string $string            
     * @return string
     */
    public static function convertCaseCamelToUnderscore($string)
    {
        return static::convertCasePascalToUnderscore($string);
    }

    /**
     * Converts a string from under_score (snake_case) to PascalCase.
     *
     * @param string $string            
     * @return string
     */
    public static function convertCaseUnderscoreToPascal($string)
    {
        return str_replace('_', '', ucwords($string, "_"));
    }

    /**
     * Converts a string from PascalCase to under_score (snake_case).
     *
     * @param string $string            
     * @return string
     */
    public static function convertCasePascalToUnderscore($string)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    /**
     *
     * @param string $haystack            
     * @param string $needle            
     * @param boolean $case_sensitive            
     * @return boolean
     */
    public static function startsWith($haystack, $needle, $case_sensitive = true)
    {
        if ($case_sensitive) {
            return substr($haystack, 0, strlen($needle)) === $needle;
        } else {
            return substr(mb_strtoupper($haystack), 0, strlen(mb_strtoupper($needle))) === mb_strtoupper($needle);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\AbstractDataType::cast()
     */
    public static function cast($string)
    {
        if (is_scalar($string)){
            $result = $string;
        } elseif (is_array($string)){
            $result = implode(EXF_LIST_SEPARATOR, $string);
        } else {
            $result =  '';
        }
        
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\AbstractDataType::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirections::ASC();
    }
    /**
     * @return integer
     */
    public function getLengtMin()
    {
        return $this->lengthin;
    }

    /**
     * @param integer $number
     * @return StringDataType
     */
    public function setLengthMin($number)
    {
        $this->lengthin = $number;
        return $this;
    }

    /**
     * @return integer
     */
    public function getLengthMax()
    {
        return $this->lengthMax;
    }

    /**
     * @param integer $number
     * @return StringDataType
     */
    public function setLengthMax($number)
    {
        $this->lengthMax = $number;
        return $this;
    }

}
?>