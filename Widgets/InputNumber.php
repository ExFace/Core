<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\IntegerDataType;

class InputNumber extends Input
{

    private $precision_max = null;
    
    private $precision_min = null;

    private $min_value = null;

    private $max_value = null;

    private $decimal_separator = null;

    private $thousand_separator = null;
    
    private $step = null;

    public function getPrecisionMax()
    {
        if (is_null($this->precision_max) && $this->getValueDataType() instanceof NumberDataType){
            $this->precision_max = $this->getValueDataType()->getPrecisionMax();
        }
        return $this->precision_max;
    }
    
    /**
     * Sets the maximum precision (number of fractional digits) for this widget.
     * 
     * @uxon-property precision_max
     * @uxon-type integer
     * 
     * @param string $number
     * @return InputNumber
     */
    public function setPrecisionMax($number)
    {
        $this->precision_max = IntegerDataType::cast($number);
        return $this;
    }
    
    public function getPrecisionMin()
    {
        if (is_null($this->precision_min) && $this->getValueDataType() instanceof NumberDataType){
            $this->precision_min = $this->getValueDataType()->getPrecisionMin();
        }
        return $this->precision_min;
    }
    
    /**
     * Sets the minimum precision (number of fractional digits) for this widget.
     * 
     * @uxon-property precision_min
     * @uxon-type integer
     * 
     * @param integer $number
     * @return InputNumber
     */
    public function setPrecisionMin($number)
    {
        $this->precision_min = IntegerDataType::cast($number);
        return $this;
    }

    /**
     * Sets a fixed precision (number of fractional digits) for this widget.
     * 
     * @uxon-property precision
     * @uxon-type integer
     * 
     * @param integer $value
     * @return \exface\Core\Widgets\InputNumber
     */
    public function setPrecision($value)
    {
        $this->precision_min = IntegerDataType::cast($value);
        $this->precision_min = IntegerDataType::cast($value);
        return $this;
    }

    public function getMinValue()
    {
        return $this->min_value;
    }
    
    /**
     * Sets the minimum value for this widget.
     * 
     * @uxon-property min_value
     * @uxon-type number
     * 
     * @param number $value
     * @return InputNumber
     */
    public function setMinValue($value)
    {
        $this->min_value = NumberDataType::cast($value);
        return $this;
    }

    public function getMaxValue()
    {
        return $this->max_value;
    }

    /**
     * Sets the maximum value for this widget.
     *
     * @uxon-property max_value
     * @uxon-type number
     *
     * @param number $number
     * @return InputNumber
     */
    public function setMaxValue($value)
    {
        $this->max_value = NumberDataType::cast($value);
        return $this;
    }

    public function getDecimalSeparator()
    {
        if (is_null($this->decimal_separator)) {
            $this->decimal_separator = $this->translate('LOCALIZATION.NUMBER.DECIMAL_SEPARATOR');
        }
        return $this->decimal_separator;
    }

    /**
     * Sets the character to separate fractional digits ("." or ",").
     * 
     * By default the current locale setting will be used.
     *
     * @uxon-property decimal_separator
     * @uxon-type string
     *
     * @param string $value
     * @return InputNumber
     */
    public function setDecimalSeparator($value)
    {
        $this->decimal_separator = $value;
        return $this;
    }

    public function getThousandsSeparator()
    {
        if (is_null($this->thousand_separator)) {
            $type = $this->getValueDataType();
            if ($type instanceof NumberDataType) {
                $this->thousand_separator = $type->getGroupSeparator();
            }
        }
        return $this->thousand_separator;
    }

    /**
     * Sets the character to separate thousands (typically "." or " ").
     *
     * By default the current locale setting will be used.
     *
     * @uxon-property thousands_separator
     * @uxon-type string
     *
     * @param string $value
     * @return InputNumber
     */
    public function setThousandsSeparator($value)
    {
        $this->thousand_separator = $value;
        return $this;
    }
    
    /**
     * @return integer|null
     */
    public function getStep()
    {
        return $this->step;
    }

    /**
     * Sets the increment step for helper-buttons (if supported by the template).
     * 
     * @uxon-property step
     * @uxon-type integer
     * 
     * @param integer $step
     * @return InputNumber
     */
    public function setStep($step)
    {
        $this->step = $step;
        return $this;
    }

}
?>