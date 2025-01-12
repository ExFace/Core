<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Exceptions\RangeException;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\TemplateRenderer\PlaceholderNotFoundError;
use exface\Core\Exceptions\TemplateRenderer\PlaceholderValueInvalidError;
use Transliterator;

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
    public function getValidatorRegex() : ?string
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getValidationDescription()
     */
    protected function getValidationDescription() : string
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $and = $translator->translate('DATATYPE.VALIDATION.AND');
        $text = '';
        if (0 < $length = $this->getLengthMin()) {
            $lengthCond = ' ≥ ' . $length;
        }
        if (0 < $length = $this->getLengthMax()) {
            $lengthFormatted = $length < 2048 ? $length : ByteSizeDataType::formatWithScale($length);
            $lengthCond .= ($lengthCond ? ' ' . $and . ' ' : '') . ' ≤ ' . $lengthFormatted;
        }
        if ($lengthCond) {
            $text .= $translator->translate('DATATYPE.VALIDATION.LENGTH_CONDITION', ['%condition%' => $lengthCond]);
        }
        if ($this->getValidatorRegex()) {
            $text = ($text ? $text . ' ' . $and . ' ' : '') . $translator->translate('DATATYPE.VALIDATION.REGEX_CONDITION', ['%regex%' => $this->getValidatorRegex()]);
        }
        
        if ($text !== '') {
            $text = $translator->translate('DATATYPE.VALIDATION.MUST') . ' ' . $text . '.';
        }
        
        return $text;
    }

    /**
     * Converts a string from under_score (snake_case) to camelCase.
     * 
     * The second (optional) argument controls if the first character is to be forced
     * to be lower case (default) or left as-is.
     * 
     * @param mixed $string
     * @param bool $lowerCaseFirst
     * @return string
     */
    public static function convertCaseUnderscoreToCamel($string, bool $lowerCaseFirst = true)
    {
        return static::convertCaseDelimiterToCamel($string ?? '', '_', $lowerCaseFirst);
    }

    /**
     * 
     * @param string $string
     * @param string $delimiter
     * @param bool $lowerCaseFirst
     * @return string
     */
    public static function convertCaseDelimiterToCamel(string $string, string $delimiter = '_', bool $lowerCaseFirst = true)
    {
        if ($lowerCaseFirst === false) {
            $firstChar = mb_substr($string, 0, 1);
            $string = mb_substr($string, 1);
        } else {
            $firstChar = '';
        }
        return $firstChar . lcfirst(static::convertCaseDelimiterToPascal($string, $delimiter));
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
        return static::convertCaseDelimiterToPascal($string ?? '', '_');
    }

    /**
     * 
     * @param string $string
     * @param string $delimiter
     * @return string
     */
    public static function convertCaseDelimiterToPascal(string $string, string $delimiter = '_') : string
    {
        return str_replace($delimiter, '', ucwords($string, $delimiter));
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
     * @param string|NULL $haystack            
     * @param string $needle            
     * @param bool $case_sensitive            
     * @return bool
     */
    public static function startsWith($haystack, string $needle, bool $case_sensitive = true) : bool
    {
        if ($haystack === null) {
            return false;
        }
        $substr = mb_substr($haystack, 0, mb_strlen($needle));
        if ($case_sensitive) {
            return $substr === $needle;
        } else {
            return strcasecmp($substr, $needle) === 0;
        }
    }
    
    /**
     *
     * @param string|NULL $haystack
     * @param string $needle
     * @param bool $case_sensitive
     * @return bool
     */
    public static function endsWith($haystack, string $needle, bool $case_sensitive = true) : bool
    {
        if ($haystack === null) {
            return false;
        }
        if ($case_sensitive) {
            return mb_substr($haystack, (-1)*mb_strlen($needle)) === $needle;
        } else {
            return mb_substr(mb_strtoupper($haystack), (-1)*mb_strlen($needle)) === mb_strtoupper($needle);
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
        if ($this::isValueLogicalNull($string)) {
            return $string;
        }
            
        $value = parent::parse($string);
        
        if ($this->isValueEmpty($value)) {
            return $value;
        }
        
        // validate length
        $length = mb_strlen($value);
        if ($this->getLengthMin() > 0 && $length < $this->getLengthMin()){
            $excValue = '';
            if (! $this->isSensitiveData()) {
                $excValue = '"' . $value . '" (' . $length . ')';
            }
            throw $this->createValidationError('The lenght of the string ' . $excValue . ' is less, than the minimum length required for data type ' . $this->getAliasWithNamespace() . ' (' . $this->getLengthMin() . ')!');
        }
        if ($this->getLengthMax() && $length > $this->getLengthMax()){
            $value = mb_substr($value, 0, $this->getLengthMax());
        }
        
        // validate against regex
        if ($this->getValidatorRegex()){
            try {
                $match = preg_match($this->getValidatorRegex(), $value);
            } catch (\Throwable $e) {
                $match = 0;
            }
            
            if (! $match){
                $excValue = '';
                if (! $this->isSensitiveData()) {
                    $excValue = '"' . $value . '"';
                }
                throw $this->createValidationError('Value ' . $excValue . ' does not match the regular expression mask "' . $this->getValidatorRegex() . '" of data type ' . $this->getAliasWithNamespace() . '!');
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
     * @return int|float
     */
    public function getLengthMin()
    {
        return $this->lengthMin;
    }

    /**
     * Minimum legnth of the string in characters - defaults to 0.
     * 
     * @uxon-property length_min
     * @uxon-type integer
     * 
     * @param int $number
     * @return StringDataType
     */
    public function setLengthMin($number) : StringDataType
    {
        $this->lengthMin = $number;
        return $this;
    }

    /**
     * @return int|float|NULL
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
     * @param int|NULL $number
     * @return StringDataType
     */
    public function setLengthMax($number) : StringDataType
    {
        $this->lengthMax = $number;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if (null !== $val = $this->getLengthMin()) {
            if ($val > 0) {
                $uxon->setProperty('length_min', $val);
            }
        }
        if (null !== $val = $this->getLengthMax()) {
            $uxon->setProperty('length_max', $val);
        }
        if (null !== $val = $this->regexValidator) {
            $uxon->setProperty('validation_regex', $this->regexValidator);
        }
        return $uxon;
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
        preg_match_all("/\[#([^#]+)#\]/", $string, $placeholders);
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
     * If `$recursive` is set to `true`, placeholders eventually contained in the replacement values
     * will be replaced true, allowing nested placeholders.
     * 
     * @param string $string
     * @param string[] $placeholders
     * @param bool $strict
     * @param bool $recursive
     * 
     * @throws PlaceholderValueInvalidError if $strict === true AND a placeholder has no value
     * 
     * @return string
     */
    public static function replacePlaceholders(string $string, array $placeholders, bool $strict = true, bool $recursive = false) : string
    {
        $phs = static::findPlaceholders($string);
        $search = [];
        $replace = [];
        foreach ($phs as $ph) {
            $phKey = '[#' . ($ph ?? '') . '#]';
            if ($strict === true && array_key_exists($ph, $placeholders) === false) {
                throw new PlaceholderNotFoundError($phKey, 'Missing value for placeholder "' . $phKey . '"!');
            }
            $search[] = $phKey;
            $replace[] = $placeholders[$ph] ?? '';
        }
        
        $replaced = str_replace($search, $replace, $string);
        
        while ($recursive === true) {
            $replacedAgain = static::replacePlaceholders($replaced, $placeholders, $strict, false);
            if ($replacedAgain === $replaced) {
                $recursive = false;
            } else {
                $replaced = $replacedAgain;
            }
        }
        
        return $replaced;
    }
    
    /**
     * Replaces a single placeholder in a string with the given value
     * 
     * @param string $string
     * @param string $placeholder
     * @param mixed $value
     * 
     * @throws \exface\Core\Exceptions\TemplateRenderer\PlaceholderValueInvalidError
     * 
     * @return string
     */
    public static function replacePlaceholder(string $string, string $placeholder, $value) : string
    {
        $search = '[#' . $placeholder . '#]';
        if (! is_scalar($value)) {
            throw new PlaceholderValueInvalidError('Cannot replace placeholder "' . $search . '" in string "' . $string . '": replacement value must be scalar, ' . gettype($value) . ' received!', null, null, $value);
        }
        return str_replace($search, $value, $string);
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
                    $substr = mb_substr($haystack, 0, $pos);
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
                    $substr = mb_substr($haystack, 0, $pos);
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
                    $substr = mb_substr($haystack, ($pos+strlen($needle)));
                }
            } else {
                $substr = strstr($haystack, $needle);
                if ($substr === false) {
                    $substr = $default;
                } else {
                    $substr = mb_substr($substr, strlen($needle));
                }
            }
        } else {
            if ($useLastOccurance) {
                $pos = strripos($haystack, $needle);
                if ($pos === false) {
                    $substr = $default;
                } else {
                    $substr = mb_substr($haystack, ($pos+strlen($needle)));
                }
            } else {
                $substr = stristr($haystack, $needle);
                if ($substr === false) {
                    $substr = $default;
                } else {
                    $substr = mb_substr($substr, strlen($needle));
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
     * Shortens the given $string to a maximum of $length characters
     * 
     * @param string $string
     * @param int $length
     * @param bool $stickToWords prevents words getting cut in the middle
     * @param bool $ellipsis adds `...` at the end if the string is really shortened
     * @param bool $endHint adds `[truncated <original length> characters]` if the string is really shortened (usefull for debug output)
     * @return string
     */
    public static function truncate(string $string, int $length, bool $stickToWords = false, bool $removeLineBreaks = false, bool $ellipsis = false, bool $endHint = false) : string
    {
        $stringLength = mb_strlen($string);
        
        if ($stringLength > $length) {
            if ($ellipsis) {
                $length = max($length - 3, 3);
            }
            if ($stickToWords === false) {
                $string = mb_substr($string, 0, $length);
            } else {
                $string = wordwrap($string, $length);
                $string = mb_substr($string, 0, mb_strpos($string, "\n"));
            }
            if ($ellipsis) {
                $string .= '...';
            }
            if ($endHint) {
                $string .= ' [truncated ' . number_format($stringLength) . ' characters]';
            }
        }
        
        if ($removeLineBreaks === true) {
            $string = static::stripLineBreaks($string);
        }
        
        return $string;
    }
    
    /**
     * 
     * @param string $string
     * @param int $limit
     * @return string[]
     */
    public static function splitLines(string $string, int $limit = null) : array
    {
        return preg_split("/\R/u", $string, ($limit > 0 ? $limit : -1));
    }
    
    /**
     * Removes line breaks for the given string keeping words intact.
     * 
     * A simple replacement of linebreaks with empty strings or space is not enough
     * because lines may contain spaces, tabs or other inivisble charaters at
     * their ends. Need to replace them properly keeping words intact.
     * 
     * IDEA probably need to hanle hypenation here somehow...
     * 
     * @param string $string
     * @return string
     */
    public static function stripLineBreaks(string $string) : string
    {
        $lines = static::splitLines($string);
        $result = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $result .= ($result !== '' ? ' ' : '') . $line;
        }
        
        return $result;
    }
    
    /**
     * Replaces all line breaks in a string by the given value - e.g. for normalization
     * 
     * By default replaces everything with `PHP_EOL`. Use `"\r\n"` for `$replace` alternatively.
     * 
     * If you don't want to replace all Unicode newlines but only CRLF style ones, set 
     * `$includeUnicodeLineBreaks` to `false`;
     * 
     * @param string $string
     * @param string $replace
     * @return string
     */
    public static function replaceLineBreaks(string $string, string $replace = PHP_EOL, bool $includeUnicodeLineBreaks = true) : string
    {
        $regex = $includeUnicodeLineBreaks ? '/\R/u' : '/(*BSR_ANYCRLF)\R/';
        return preg_replace($regex, $replace, $string);
    }
    
    /**
     * 
     * @param string $string
     * @param string $indent
     * @return string
     */
    public static function indent(string $string, $indent = '  ') : string
    {
        return $indent .= preg_replace('/(\\R)(.*)/', '\\1' . preg_quote($indent, '/') . '\\2', $string);
    }
    
    /**
     * 
     * @param string $text
     * @param string $puct
     * @return string
     */
    public static function endSentence(string $text, string $puct = '.') : string
    {
        $text = trim($text);
        $end = mb_substr($text, -1);
        switch ($end) {
            case '.':
            case '?':
            case '!':
                return $text;
        }
        
        return $text . $puct;
    }

    /**
     * Transliterate a given string using ICU standard rules
     * 
     * See https://unicode-org.github.io/icu/userguide/transforms/general/ for available rules
     * 
     * Examples:
     * 
     * - `transliterate('Änderung')` -> Anderung
     * - `transliterate('Änderung', ':: Any-Latin; :: Latin-ASCII; :: Lower()')` -> anderung
     * - `transliterate('ä/B', ':: Any-Latin; [:Punctuation:] Remove;')` -> a b
     * - `transliterate('Aufgaben im Überblick', ':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;)` -> aufgaben im uberblick
     * 
     * @link https://unicode-org.github.io/icu/userguide/transforms/general/
     * 
     * @param string $string
     * @param string $translitRules
     * @param int $direction
     * @throws \exface\Core\Exceptions\RuntimeException
     * @return string
     */
    public static function transliterate(string $string, string $translitRules = ':: Any-Latin; :: Latin-ASCII;', int $direction = Transliterator::FORWARD) : string
    {
        if ($string === '') {
            return $string;
        }
        $transliterator = \Transliterator::createFromRules($translitRules);
        $result = $transliterator->transliterate($string);
        // Alternative with slightly different syntax. Need testing to find out, which is better
        // $result = transliterator_transliterate($translitRules, 'Any-Latin; Latin-ASCII;');
        if ($result === false) {
            throw new RuntimeException('Cannot transliterate "' . static::truncate($string, 100, false, true, true, true) . '": ' . $transliterator->getErrorMessage());
        }
        return $result;
    }
    
    /**
     * Returns TRUE if the given string is one enclosed is quotes (single or double quotes) and FALSE otherwise
     * 
     * Currently this does not check, if there are also some closing quotes in the middle of the string.
     * Possible enhanced solution: https://stackoverflow.com/questions/74963883/php-regular-expression-to-grab-values-enclosed-in-double-quotes
     * 
     * @param string $str
     * @return bool
     */
    public static function isQuotedString(string $str) : bool
    {
        $str = trim($str);
        $firstChar = mb_substr($str, 0, 1);
        if ($firstChar !== '"' && $firstChar !== "'") {
            return false;
        }
        $lastChar = mb_substr($str, -1);
        if ($lastChar !== '"' && $lastChar !== "'") {
            return false;
        }
        return true;
    }

    /**
     * Determines, which line break characters are used in a string and returns them as an array
     * 
     * @param string $str
     * @return array
     */
    public static function findLineBreakChars(string $str) : array
    {
        $matches = [];
        preg_match('/\R/', $str, $matches);
        return array_unique($matches);
    }
}