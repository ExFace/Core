<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypeValidationError;
use exface\Core\CommonLogic\Constants\SortingDirections;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;

class NumberDataType extends AbstractDataType
{
    private $precisionMin = 0;
    
    private $precisionMax = null;
    
    private $min = null;
    
    private $max = null;

    public static function cast($string)
    {
        if (is_numeric($string)) {
            // Decimal numbers
            return $string;
        } elseif ($string === '' || is_null($string)) {
            return null;
        } elseif (strpos($string, '0x') === 0) {
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
            throw new DataTypeValidationError('Cannot convert "' . $string . '" to a number!');
            return '';
        }
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
     * @param integer $precisionMin
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
     * @param number $max
     * @return NumberDataType
     */
    public function setMax($max)
    {
        $this->max = $max;
        return $this;
    }

}
?>