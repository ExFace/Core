<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\Constants\SortingDirections;
use exface\Core\Exceptions\DataTypeValidationError;

class StringDataType extends AbstractDataType
{
    private $lengthMin = 0;
    
    private $lengthMax = null;
    
    private $regexValidator = null;

    /**
     * @return string|null
     */
    public function getRegexValidator()
    {
        return $this->regexValidator;
    }

    /**
     * @param string $regularExpression
     * @return StringDataType
     */
    public function setRegexValidator($regularExpression)
    {
        $this->regexValidator = $regularExpression;
        return $this;
    }

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
     * @param string $haystack
     * @param string $needle
     * @param boolean $case_sensitive
     * @return boolean
     */
    public static function endsWith($haystack, $needle, $case_sensitive = true)
    {
        if ($case_sensitive) {
            return substr($haystack, (-1)*strlen($needle)) === $needle;
        } else {
            return substr(mb_strtoupper($haystack), (-1)*strlen(mb_strtoupper($needle))) === mb_strtoupper($needle);
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
    
    public function parse($string){
        $value = parent::parse($string);
        
        // validate length
        $length = mb_strlen($value);
        if ($this->getLengtMin() > 0 && $length < $this->getLengtMin()){
            throw new DataTypeValidationError('The lenght of the string "' . $value . '" (' . $length . ') is less, than the minimum length required for data type ' . $this->getAliasWithNamespace() . ' (' . $this->getLengtMin() . ')!');
        }
        if ($this->getLengthMax() && $length > $this->getLengthMax()){
            $value = substr($value, 0, $this->getLengthMax());
        }
        
        // validate against regex
        if ($this->getRegexValidator()){
            try {
                $match = preg_match("'" . $this->getRegexValidator() . "'", $value);
            } catch (\Throwable $e) {
                $match = 0;
            }
            
            if (! $match){
                throw new DataTypeValidationError('Value "' . $value . '" does not match the regular expression mask "' . $this->getRegexValidator() . '" of data type ' . $this->getAliasWithNamespace() . '!');
            }
        }
        
        return $value;        
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