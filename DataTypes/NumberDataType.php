<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\CommonLogic\Constants\SortingDirections;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

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
    private $precisionMin = 0;
    
    private $precisionMax = null;
    
    private $min = null;
    
    private $max = null;
    
    private $base = 10;

    public static function cast($string)
    {
        if (is_numeric($string)) {
            // Decimal numbers
            return $string;
        } elseif ($string === '' || is_null($string)) {
            return null;
        } elseif (mb_strtoupper(substr($string, 0, 2)) === '0X') {
            // Hexadecimal numbers in '0x....'-Notation
            return $string;
        } elseif (strcasecmp($string, 'true') === 0) {
            return 1;
        } elseif (strcasecmp($string, 'false') === 0) {
            return 0;
        } elseif (strcasecmp($string, 'null') === 0) {
            return null;
        } else {
            $string = trim($string);
            $matches = array();
            preg_match('!-?\d+[,\.]?\d*+!', str_replace(' ', '', $string), $matches);
            $match = str_replace(',', '.', $matches[0]);
            if (is_numeric($match)) {
                return $match;
            }
            throw new DataTypeCastingError('Cannot convert "' . $string . '" to a number!');
            return '';
        }
    }
    
    public function parse($string)
    {
        $number = parent::parse($string);
        
        if (! is_null($this->getMin()) && $number < $this->getMin()) {
            throw new DataTypeValidationError($this, $number . ' is less than the minimum of ' . $this->getMin() . ' allowed for data type ' . $this->getAliasWithNamespace() . '!');
        }
        
        if (! is_null($this->getMax()) && $number > $this->getMax()) {
            throw new DataTypeValidationError($this, $number . ' is greater than the maximum of ' . $this->getMax() . ' allowed for data type ' . $this->getAliasWithNamespace() . '!');
        }
        
        return $number;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\AbstractDataType::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirections::DESC();
    }
    /**
     * @return integer
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
     * @return integer
     */
    public function getPrecisionMax()
    {
        return $this->precisionMax;
    }

    /**
     * Sets a maximum precision (number of fractional digits).
     * 
     * Values will be rounded to this number of fractional digits
     * without raising errors.
     * 
     * @uxon-property precision_max
     * @uxon-type integer
     * 
     * @param integer $precisionMax
     * @return NumberDataType
     */
    public function setPrecisionMax($precisionMax)
    {
        $value = intval($precisionMax);
        if ($this->getPrecisionMin() && $value < $this->getPrecisionMin()){
            throw new DataTypeConfigurationError($this, 'Minimum precision ("' . $value . '") of ' . $this->getAliasWithNamespace() . ' less than maximum precision ("' . $this->getPrecisionMax() . '")!', '6XALZHW');
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


}
?>