<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Exceptions\UnderflowException;
use exface\Core\Exceptions\RangeException;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

/**
 * Basic data type for textual values.
 * 
 * Strings can contain any characters, but can be restricted in length and
 * validating using regular expressions.
 * 
 * @author Andrej Kabachnik
 *
 */
class StringDataType extends AbstractDataType
{
    private $lengthMin = 0;
    
    private $lengthMax = null;
    
    private $regexValidator = null;

    /**
     * @return string|null
     */
    public function getValidatorRegex()
    {
        return $this->regexValidator;
    }

    /**
     * Defines a regular expression to validate values of this data type.
     * 
     * Use regular expressions compatible with PHP preg_match(). A good
     * tool to create and test regular expressions can be found here:
     * https://regex101.com/.
     * 
     * @uxon-property validator_regex
     * @uxon-type string
     * 
     * @param string $regularExpression
     * @return StringDataType
     */
    public function setValidatorRegex($regularExpression)
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
        $substr = substr($haystack, 0, strlen($needle));
        if ($case_sensitive) {
            return $substr === $needle;
        } else {
            return strcasecmp($substr, $needle) === 0;
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
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::cast()
     */
    public static function cast($string)
    {
        if (is_scalar($string) === true || static::isValueEmpty($string) === true){
            return $string;
        } elseif (is_array($string) === true){
            return implode(EXF_LIST_SEPARATOR, $string);
        } else {
            return  '';
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::parse()
     */
    public function parse($string)
    {
        $value = parent::parse($string);
        
        // validate length
        $length = mb_strlen($value);
        if ($this->getLengthMin() > 0 && $length < $this->getLengthMin()){
            throw $this->createValidationError('The lenght of the string "' . $value . '" (' . $length . ') is less, than the minimum length required for data type ' . $this->getAliasWithNamespace() . ' (' . $this->getLengthMin() . ')!');
        }
        if ($this->getLengthMax() && $length > $this->getLengthMax()){
            $value = substr($value, 0, $this->getLengthMax());
        }
        
        // validate against regex
        if ($this->getValidatorRegex()){
            try {
                $match = preg_match($this->getValidatorRegex(), $value);
            } catch (\Throwable $e) {
                $match = 0;
            }
            
            if (! $match){
                throw $this->createValidationError('Value "' . $value . '" does not match the regular expression mask "' . $this->getValidatorRegex() . '" of data type ' . $this->getAliasWithNamespace() . '!');
            }
        }
        
        return $value;        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirectionsDataType::ASC($this->getWorkbench());
    }
    /**
     * @return integer
     */
    public function getLengthMin()
    {
        return $this->lengthin;
    }

    /**
     * Minimum legnth of the string in characters - defaults to 0.
     * 
     * @uxon-property length_min
     * @uxon-type integer
     * 
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
     * Maximum legnth of the string in characters.
     * 
     * @uxon-property length_max
     * @uxon-type integer
     * 
     * @param integer $number
     * @return StringDataType
     */
    public function setLengthMax($number)
    {
        $this->lengthMax = $number;
        return $this;
    }
    
    /**
     * Returns an array of ExFace-placeholders found in a string.
     * E.g. will return ["name", "id"] for string "Object [#name#] has the id [#id#]"
     *
     * @param string $string
     * @return array
     */
    public static function findPlaceholders($string)
    {
        $placeholders = array();
        preg_match_all("/\[#([^\]\[#]+)#\]/", $string, $placeholders);
        return is_array($placeholders[1]) ? $placeholders[1] : array();
    }
    
    /**
     * Looks for placeholders ([#...#]) in a string and replaces them with values from
     * the given array, where the key matches the placeholder.
     * 
     * Examples:
     * - replacePlaceholder('Hello [#world#][#dot#]', ['world'=>'WORLD', 'dot'=>'!']) -> "Hello WORLD!"
     * - replacePlaceholder('Hello [#world#][#dot#]', ['world'=>'WORLD']) -> exception
     * - replacePlaceholder('Hello [#world#][#dot#]', ['world'=>'WORLD'], false) -> "Hello WORLD"
     * 
     * @param string $string
     * @param string[] $placeholders
     * @param bool $strict
     * 
     * @throws RangeException if no value is found for a placeholder
     * 
     * @return string
     */
    public static function replacePlaceholders(string $string, array $placeholders, bool $strict = true) : string
    {
        $phs = static::findPlaceholders($string);
        $search = [];
        $replace = [];
        foreach ($phs as $ph) {
            if ($strict === true && isset($placeholders[$ph]) === false) {
                throw new RangeException('Missing value for "' . $ph . '"!');
            }
            $search[] = '[#' . $ph . '#]';
            $replace[] = $placeholders[$ph] ?? '';
        }
        return str_replace($search, $replace, $string);
    }
    
    /**
     * Returns the part of the given string ($haystack) preceeding the first occurrence of $needle.
     * 
     * Examples:
     * - substringBefore('one, two, three', ',') => 'one'
     * - substringBefore('one, two, three', ',', false, true) => 'one, two'
     * - substringBefore('one, two, three', ';') => false
     * - substringBefore('one, two, three', ';', 'one, two, three') => 'one, two, three'
     * 
     * Using the optional parameters you can make the search case sensitive and
     * search for the last occurrence instead of the first one.
     * 
     * Returns $default if the $needle was not found.
     * 
     * @param string $haystack
     * @param string $needle
     * @param mixed $default
     * @param bool $caseSensitive
     * @param bool $useLastOccurance
     * @return string|boolean
     */
    public static function substringBefore(string $haystack, string $needle, $default = false, bool $caseSensitive = false, bool $useLastOccurance = false)
    {
        $substr = '';
        if ($caseSensitive === true) {
            if ($useLastOccurance === true) {
                $pos = strrpos($haystack, $needle);
                if ($pos === false) {
                    $substr = $default;
                } else {
                    $substr = substr($haystack, 0, $pos);
                }
            } else {
                $substr = strstr($haystack, $needle, true);
                if ($substr === false) {
                    $substr = $default;
                }
            }
        } else {
            if ($useLastOccurance) {
                $pos = strripos($haystack, $needle);
                if ($pos === false) {
                    $substr = $default;
                } else {
                    $substr = substr($haystack, 0, $pos);
                }
            } else {
                $substr = stristr($haystack, $needle, true);
                if ($substr === false) {
                    $substr = $default;
                }
            }
        }
        return $substr;
    }
    
    /**
     * Returns the part of the given string ($haystack) following the first occurrence of $needle.
     * 
     * Using the optional parameters you can make the search case sensitive and
     * search for the last occurrence instead of the first one.
     * 
     * @param string $haystack
     * @param string $needle
     * @param mixed $default
     * @param bool $caseSensitive
     * @param bool $useLastOccurance
     * @return string|boolean
     */
    public static function substringAfter(string $haystack, string $needle, $default = false, bool $caseSensitive = false, bool $useLastOccurance = false)
    {
        $substr = '';
        if ($caseSensitive === true) {
            if ($useLastOccurance === true) {
                $pos = strrpos($haystack, $needle);
                if ($pos === false) {
                    $substr = $default;
                } else {
                    $substr = substr($haystack, ($pos+strlen($needle)));
                }
            } else {
                $substr = strstr($haystack, $needle);
                if ($substr === false) {
                    $substr = $default;
                } else {
                    $substr = substr($substr, strlen($needle));
                }
            }
        } else {
            if ($useLastOccurance) {
                $pos = strripos($haystack, $needle);
                if ($pos === false) {
                    $substr = $default;
                } else {
                    $substr = substr($haystack, ($pos+strlen($needle)));
                }
            } else {
                $substr = stristr($haystack, $needle);
                if ($substr === false) {
                    $substr = $default;
                } else {
                    $substr = substr($substr, strlen($needle));
                }
            }
        }
        return $substr;
    }
    
    /**
     * Returns the given string in UTF-8 encoding.
     * 
     * If no $originalEncoding is provided, mb_detect_encoding() will be used to attemt to detect it.
     * 
     * @param string $string
     * @param string $originalEncoding
     * @return string
     */
    public static function encodeUTF8(string $string, string $originalEncoding = null) {
        return mb_convert_encoding($string, 'UTF-8', ($originalEncoding ?? mb_detect_encoding($string)));
    }
    
    /**
     * 
     * @param string $string
     * @param int $length
     * @param bool $stickToWords
     * @return string
     */
    public static function truncate(string $string, int $length, bool $stickToWords) : string
    {
        if ($stickToWords === false) {
            return mb_substr($string, 0, $length);
        } else {
            if (strlen($string) > $length) {
                $string = wordwrap($string, $length);
                $string = mb_substr($string, 0, mb_strpos($string, "\n"));
            }
            return $string;
        }
    }
}
?>