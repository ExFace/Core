<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\DataTypeFactory;

/**
 * Basic data type for numeric values.
 * 
 * The base, precision, as well as minimum and maximum values can be configured.
 * Both, "." and "," are recognized as fractional separators.
 * 
 * @author Andrej Kabachnik
 *
 */
class NumberDataType extends AbstractDataType
{
    private $precisionMin = null;
    
    private $precisionMax = null;
    
    private $min = null;
    
    private $max = null;
    
    private $base = 10;
    
    private $groupDigits = null;
    
    private $groupLength = null;
    
    private $groupSeparator = null;
    
    private $emptyFormat = null;
    
    private $showPlusSign = null;
    
    private $prefix = null;
    
    private $suffix = null;

    /**
     *
     * {@inheritdoc}
     * @see AbstractDataType::cast()
     */
    public static function cast($string)
    {
        switch (true) {
            // Decimal numbers
            case is_numeric($string) === true:
                if (is_string($string)) {
                    // Return the string as an int if it represents an integer value or as a float if it represents a float
                    // That is important to assure that for exanple 1.00 and 1 is treated as the same value in comparisons
                    $float = floatval($string);
                    $int = intval($string);
                    return floatval($int) === $float ? $int : $float; 
                }
                return $string;
            case is_bool($string):
                return $string === true ? 1 : 0;
            // Return NULL for casting empty values as an empty string '' actually is not a number!
            case static::isValueEmpty($string) === true:
                return null;
            // All the subsequent cases deal with strings, so throw error here if it is not a string
            case (! is_string($string)):
                throw new DataTypeCastingError('Cannot cast "' . gettype($string) . '" to number!');
            // Hexadecimal numbers in '0x....'-Notation
            case mb_strtoupper(substr($string, 0, 2)) === '0X':
                return $string;
            case strcasecmp($string, 'true') === 0:
                return 1;
            case strcasecmp($string, 'false') === 0:
                return 0;
            // NULL constant
            // TODO #null-or-NULL the NULL constant is not a number, but do we still need it here?
            case static::isValueLogicalNull($string) === true:
                return null;
            default:
                $trimmed = str_replace(' ', '', trim($string));
                $matches = array();
                preg_match_all('!^(-?\d+([,\.])?)+$!', $trimmed, $matches);
                if (empty($matches[0]) === false) {
                    $decimalSep = $matches[2][0];
                    if ($decimalSep === ',') {
                        $number = str_replace('.', '', $trimmed);
                        $number = str_replace($decimalSep, '.', $number);
                    } else {
                        $number = str_replace(',', '', $trimmed);
                    }
                    if (is_numeric($number)) {
                        return $number;
                    }
                }            
                throw new DataTypeCastingError('Cannot convert "' . $string . '" to a number!');
        }
        return null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::parse()
     */
    public function parse($string)
    {
        if (is_string($string)) {
            $string = trim($string);
            if (null !== ($pfx = $this->getPrefix()) && StringDataType::startsWith($string, $pfx, false)) {
                $string = trim(mb_substr($string, strlen($pfx)));
            }
            if (null !== ($sfx = $this->getSuffix()) && StringDataType::endsWith($string, $sfx, false)) {
                $string = trim(mb_substr($string, 0, (-1) * strlen($sfx)));
            }
        }
        
        try {
            $number = self::cast($string);
        } catch (\Throwable $e) {
            throw $this->createValidationParseError($string, null, null, $e->getMessage(), $e->getCode(), $e);
        }
        
        if ($string === $this->getEmptyFormat()) {
            return null;
        }
        
        if (! $this->isValueEmpty($number)) {
            if (! is_null($this->getMin()) && $number < $this->getMin()) {
                throw $this->createValidationRuleError($number, $number . ' is less than the minimum of ' . $this->getMin() . ' allowed for data type ' . $this->getAliasWithNamespace() . '!', false);
            }
            
            if (! is_null($this->getMax()) && $number > $this->getMax()) {
                throw $this->createValidationRuleError($number, $number . ' is greater than the maximum of ' . $this->getMax() . ' allowed for data type ' . $this->getAliasWithNamespace() . '!', false);
            }
        }
        
        return $number;
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
        if ($this->getMin() !== null) {
            $minMaxCond = ' ≥ ' . $this->getMin();
        }
        if ($this->getMax() !== null) {
            $minMaxCond .= ($minMaxCond ? ' ' . $and . ' ' : '') . ' ≤ ' . $this->getMax();
        }
        if ($minMaxCond) {
            $text .= $translator->translate('DATATYPE.VALIDATION.MINMAX_CONDITION', ['%condition%' => $minMaxCond]);
        }
        
        if ($text !== '') {
            $text = $translator->translate('DATATYPE.VALIDATION.MUST') . ' ' . $text . '.';
        }
        
        return $text;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirectionsDataType::DESC($this->getWorkbench());
    }
    
    /**
     * @return integer|null
     */
    public function getPrecisionMin()
    {
        return $this->precisionMin;
    }

    /**
     * Sets the minimum precision (number of fractional digits).
     * 
     * Even if a value has less fractional digits zeros will be added.
     * 
     * @uxon-property precision_min
     * @uxon-type integer
     * 
     * @param integer $precisionMin
     * @return NumberDataType
     */
    public function setPrecisionMin($precisionMin)
    {
        $value = intval($precisionMin);
        if ($value > 100) {
            throw new DataTypeConfigurationError($this, 'Number precision value too large: "' . $value . '"!');
        }
        if (null !== ($max = $this->getPrecisionMax()) && $value > $max){
            throw new DataTypeConfigurationError($this, 'Maximum precision ("' . $value . '") of ' . $this->getAliasWithNamespace() . ' greater than minimum precision ("' . $max . '")!', '6XALZHW');
        }
        $this->precisionMin = $value;
        return $this;
    }

    /**
     * Returns the maximum number of fraction digits (precision) or NULL if unlimited.
     * 
     * @return integer|null
     */
    public function getPrecisionMax()
    {
        return $this->precisionMax;
    }

    /**
     * Sets a maximum precision (number of fractional digits) - unlimited (null) by default.
     * 
     * Values will be rounded to this number of fractional digits
     * without raising errors.
     * 
     * @uxon-property precision_max
     * @uxon-type integer
     * 
     * @param integer|null $precisionMax
     * @return NumberDataType
     */
    public function setPrecisionMax($precisionMax)
    {
        if (is_null($precisionMax)) {
            $value = null;
        } else {
            $value = intval($precisionMax);
            if ($value > 100) {
                throw new DataTypeConfigurationError($this, 'Number precision value too large: "' . $value . '"!');
            }
            if (null !== ($min = $this->getPrecisionMin()) && $value < $min){
                throw new DataTypeConfigurationError($this, 'Maximum precision ("' . $value . '") of ' . $this->getAliasWithNamespace() . ' less than minimum precision ("' . $min . '")!', '6XALZHW');
            }
        }
        $this->precisionMax = $value;
        return $this;
    }
    
    /**
     * Sets a fixed precision (number of fractional digits).
     * 
     * All values will forcibely have this number of fractional digits
     * regardless of their actual precision. Values with more fractional
     * digits will be rounded.
     * 
     * @uxon-property precision
     * @uxon-type integer
     * 
     * @param integer $number
     * @return \exface\Core\DataTypes\NumberDataType
     */
    public function setPrecision($number)
    {
        $this->precisionMax = intval($number);
        $this->precisionMin = intval($number);
        return $this;
    }

    /**
     * @return number
     */
    public function getMin()
    {
        return $this->min;
    }

    /**
     * Minimum value.
     * 
     * @uxon-property min
     * @uxon-type number
     * 
     * @param number $min
     * @return NumberDataType
     */
    public function setMin($min)
    {
        $this->min = $min;
        return $this;
    }

    /**
     * @return number
     */
    public function getMax()
    {
        return $this->max;
    }

    /**
     * Maximum value.
     * 
     * @uxon-property max
     * @uxon-type number
     * 
     * @param number $max
     * @return NumberDataType
     */
    public function setMax($max)
    {
        $this->max = $max;
        return $this;
    }
    
    /**
     * 
     * @return integer
     */
    public function getBase()
    {
        if (is_null($this->base)){
            return 10;
        }
        return $this->base;
    }

    /**
     * Sets the base of the number - 10 by default (16 for numbers starting with 0x or 0X).
     * 
     * @uxon-property base
     * @uxon-type integer
     * 
     * @param number $base
     */
    public function setBase($base)
    {
        $this->base = $base;
        return $this;
    }
    
    /**
     * @return boolean
     */
    public function getGroupDigits()
    {
        return $this->groupDigits ?? true;
    }

    /**
     * If set to TRUE, digits will be separated in groups of group_length.
     * 
     * @uxon-property group_digits
     * @uxon-type boolean
     * 
     * @param boolean $groupDigits
     */
    public function setGroupDigits($true_or_false)
    {
        $this->groupDigits = BooleanDataType::cast($true_or_false);
        return $this;
    }

    /**
     * @return int
     */
    public function getGroupLength()
    {
        return $this->groupLength ?? 3;
    }
    
    /**
     * Sets the length of a digit group if group_digits is enabled.
     * 
     * @uxon-property group_length
     * @uxon-type integer
     * 
     * @param integer $groupDigits
     */
    public function setGroupLength($number)
    {
        $this->groupLength = NumberDataType::cast($number);
        return $this;
    }
    
    /**
     * Returns the digit group separator or NULL if not defined.
     * 
     * @return string|null
     */
    public function getGroupSeparator()
    {
        if (is_null($this->groupSeparator)) {
            $this->groupSeparator = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.NUMBER.THOUSANDS_SEPARATOR');
        }
        return $this->groupSeparator;
    }

    /**
     * Sets a language-agnostic digit group separator for this data type.
     * 
     * If not set and digit grouping is enabled, the default separator for the current language
     * will be used automatically.
     * 
     * @uxon-property group_separator
     * @uxon-type string
     * 
     * @param string $groupSeparator
     * @return NumberDataType
     */
    public function setGroupSeparator($groupSeparator)
    {
        $this->groupSeparator = $groupSeparator;
        return $this;
    }
    
    /**
     * Returns the decimal separator for the current locale.
     * 
     * @return string
     */
    public function getDecimalSeparator() : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.NUMBER.DECIMAL_SEPARATOR');
    }
    
    /**
     * 
     * @return string
     */
    public function getEmptyFormat() : string
    {
        return $this->emptyFormat ?? $this->getEmptyText() ?? '';
    }
    
    /**
     * What to display when formatting empty values - e.g. `?` or `N/A` - empty string by default.
     * 
     * @uxon-property empty_format
     * @uxon-type string
     * 
     * @param string $value
     * @return NumberDataType
     */
    public function setEmptyFormat(string $value) : NumberDataType
    {
        $this->emptyFormat = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::format()
     */
    public function format($value = null) : string
    {
        $num = $this->parse($value);
        
        if ($num === null || $num === '' || $num === EXF_LOGICAL_NULL) {
            return $this->getEmptyFormat();
        }
        
        $pMin = $this->getPrecisionMin();
        $pMax = $this->getPrecisionMax();
        
        if ($pMax === 0 || ($pMax !== null && $pMin !== null && $pMax <= $pMin)) {
            $decimals = $pMax;
        } else {
            $decPart = explode('.', strval($num))[1] ?? '';
            $pReal = strlen(rtrim($decPart, '0'));
            switch (true) {
                case $pMax === null && $pMin !== null:
                    $decimals = min([$pMin, $pReal]);
                    break;
                case $pMax !== null && $pMin !== null:
                    $decimals = min([$pMin, $pMax]);
                    break;
                case $pMin !== null:
                    $decimals = min([$pMax, $pReal]);
                    break;
                default:
                    $decimals = $pReal;
            }
        }
        
        $float = floatval($num);
        $sign = $this->getShowPlusSign() && $float > 0 ? '+' : '';
        
        $formatted = $sign . number_format($float, $decimals, $this->getDecimalSeparator(), $this->getGroupSeparator());
        
        if (null !== $pfx = $this->getPrefix()) {
            $formatted = $pfx . $formatted;
        }
        
        if (null !== $sfx = $this->getSuffix()) {
            $formatted .= $sfx;
        }
        
        return $formatted;
    }
    
    /**
     * 
     * @param int|float|NULL|string $value
     * @param WorkbenchInterface $workbench
     * @param string $emptyFormat
     * @param int $precisionMin
     * @param int $precisionMax
     * @param string $groupSeparator
     * @param int $groupLength
     * @param string $decimalSeparator
     * @return string
     */
    public static function formatNumberLocalized($value, WorkbenchInterface $workbench, $emptyFormat = '', int $precisionMin = null, int $precisionMax = null, string $groupSeparator = null, int $groupLength = null, string $decimalSeparator = null) : string
    {
        if ($value === null || $value === '' || $value === EXF_LOGICAL_NULL) {
            return $emptyFormat;
        }
        
        /* @var $type \exface\Core\DataTypes\NumberDataType */
        $type = DataTypeFactory::createFromString($workbench, NumberDataType::class);
        
        if ($emptyFormat !== '') {
            $type->setEmptyFormat($emptyFormat);
        }
        if ($precisionMin !== null) {
            $type->setPrecisionMin($precisionMin);
        }
        if ($precisionMax !== null) {
            $type->setPrecisionMax($precisionMax);
        }
        /* TODO why can't we set a custom decimal separator for a numeric type???
        if ($decimalSeparator !== null) {
            $type->setDecimalSeparator($decimalSeparator);
        }*/
        if ($groupSeparator !== null) {
            $type->setGroupSeparator($groupSeparator);
        }
        if ($groupLength !== null) {
            $type->setGroupLength($groupLength);
        }
        
        return $type->format($value);
    }
    
    /**
     * 
     * @return bool
     */
    public function getShowPlusSign() : bool
    {
        return $this->showPlusSign ?? false;
    }
    
    /**
     * Set to TRUE to show the plus-sign in front of positive numbers
     * 
     * @uxon-property show_plus_sign
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return NumberDataType
     */
    public function setShowPlusSign(bool $value) : NumberDataType
    {
        $this->showPlusSign = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getPrefix() : ?string
    {
        return $this->prefix;
    }
    
    /**
     * Adds a prefix in front of the number when formatting - e.g. a symbol
     * 
     * @uxon-property prefix
     * @uxon-type string
     * @uxon-translatable true
     * 
     * @param string $value
     * @return NumberDataType
     */
    public function setPrefix(string $value) : NumberDataType
    {
        $this->prefix = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getSuffix() : ?string
    {
        return $this->suffix;
    }
    
    /**
     * Adds a suffix after the number when formatting - e.g. a measurement unit
     * 
     * @uxon-property suffix
     * @uxon-type string
     * @uxon-translatable true
     * 
     * @param string $value
     * @return NumberDataType
     */
    public function setSuffix(string $value) : NumberDataType
    {
        $this->suffix = $value;
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
        
        if (null !== $val = $this->precisionMin) {
            $uxon->setProperty('precision_min', $val);
        }
        if (null !== $val = $this->precisionMax) {
            $uxon->setProperty('precision_max', $val);
        }
        if (null !== $val = $this->min) {
            $uxon->setProperty('min', $val);
        }
        if (null !== $val = $this->max) {
            $uxon->setProperty('max', $val);
        }
        if (null !== $val = $this->prefix) {
            $uxon->setProperty('prefix', $val);
        }
        if (null !== $val = $this->suffix) {
            $uxon->setProperty('suffix', $val);
        }
        if (null !== $val = $this->showPlusSign) {
            $uxon->setProperty('show_plus_sign', $val);
        }
        if (null !== $val = $this->emptyFormat) {
            $uxon->setProperty('empty_format', $val);
        }
        if (null !== $val = $this->groupDigits) {
            $uxon->setProperty('group_digits', $val);
        }
        if (null !== $val = $this->groupLength) {
            $uxon->setProperty('group_length', $val);
        }
        if (null !== $val = $this->groupSeparator) {
            $uxon->setProperty('group_separator', $val);
        }
        
        return $uxon;
    }

    /**
     * @param $value
     * @return string|null
     */
    public function getValidationErrorReason($value) : ?string
    {
        $customText = parent::getValidationErrorReason($value);
        if (! $customText) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            $customText = $translator->translate('DATATYPE.DATE.ERROR_INVALID_VALUE', ['%value%' => $value]);
        }
        return $customText;
    }
}