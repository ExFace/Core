<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;

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
    
    private $groupDigits = true;
    
    private $groupLength = 3;
    
    private $groupSeparator = null;

    /**
     *
     * {@inheritdoc}
     * @see AbstractDataType::cast()
     */
    public static function cast($string)
    {
        if (is_numeric($string) === true) {
            // Decimal numbers
            return $string;
        } elseif (static::isValueEmpty($string) === true) {
            // Return NULL for casting empty values as an empty string '' actually is not a number!
            return null;
        } elseif (mb_strtoupper(substr($string, 0, 2)) === '0X') {
            // Hexadecimal numbers in '0x....'-Notation
            return $string;
        } elseif (strcasecmp($string, 'true') === 0) {
            return 1;
        } elseif (strcasecmp($string, 'false') === 0) {
            return 0;
        } elseif (static::isValueLogicalNull($string) === true) {
            return null;
        } else {
            $string = trim($string);
            $matches = array();
            preg_match_all('!^(-?\d+([,\.])?)+$!', str_replace(' ', '', $string), $matches);
            if (empty($matches[0]) === false) {
                $decimalSep = $matches[2][0];
                if ($decimalSep === ',') {
                    $number = str_replace('.', '', $string);
                    $number = str_replace($decimalSep, '.', $number);
                } else {
                    $number = str_replace(',', '', $string);
                }
                if (is_numeric($number)) {
                    return $number;
                }
            }            
            throw new DataTypeCastingError('Cannot convert "' . $string . '" to a number!');
            return '';
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::parse()
     */
    public function parse($string)
    {
        try {
            $number = parent::parse($string);
        } catch (\Throwable $e) {
            throw $this->createValidationError($e->getMessage(), $e->getCode(), $e);
        }
        
        if (! is_null($this->getMin()) && $number < $this->getMin()) {
            throw $this->createValidationError($number . ' is less than the minimum of ' . $this->getMin() . ' allowed for data type ' . $this->getAliasWithNamespace() . '!');
        }
        
        if (! is_null($this->getMax()) && $number > $this->getMax()) {
            throw $this->createValidationError($number . ' is greater than the maximum of ' . $this->getMax() . ' allowed for data type ' . $this->getAliasWithNamespace() . '!');
        }
        
        return $number;
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
        if ($this->getPrecisionMax() && $value > $this->getPrecisionMax()){
            throw new DataTypeConfigurationError($this, 'Maximum precision ("' . $value . '") of ' . $this->getAliasWithNamespace() . ' greater than minimum precision ("' . $this->getPrecisionMin() . '")!', '6XALZHW');
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
            if ($this->getPrecisionMin() && $value < $this->getPrecisionMin()){
                throw new DataTypeConfigurationError($this, 'Minimum precision ("' . $value . '") of ' . $this->getAliasWithNamespace() . ' less than maximum precision ("' . $this->getPrecisionMax() . '")!', '6XALZHW');
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
        return $this->groupDigits;
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
     * @return boolean
     */
    public function getGroupLength()
    {
        return $this->groupLength;
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
}
?>