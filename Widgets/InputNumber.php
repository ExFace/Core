<?php
namespace exface\Core\Widgets;

class InputNumber extends Input
{

    private $precision = 3;

    private $min_value = false;

    private $max_value = false;

    private $decimal_separator = ',';

    private $thousand_separator = ' ';

    private $prefix = '';

    private $suffix = '';

    public function getPrecision()
    {
        return $this->precision;
    }

    public function setPrecision($value)
    {
        $this->precision = $value;
    }

    public function getMinValue()
    {
        return $this->min_value;
    }

    public function setMinValue($value)
    {
        $this->min_value = $value;
    }

    public function getMaxValue()
    {
        return $this->max_value;
    }

    public function setMaxValue($value)
    {
        $this->max_value = $value;
    }

    public function getDecimalSeparator()
    {
        return $this->decimal_separator;
    }

    public function setDecimalSeparator($value)
    {
        $this->decimal_separator = $value;
    }

    public function getThousandSeparator()
    {
        return $this->thousand_separator;
    }

    public function setThousandSeparator($value)
    {
        $this->thousand_separator = $value;
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function setPrefix($value)
    {
        $this->prefix = $value;
    }

    public function getSuffix()
    {
        return $this->suffix;
    }

    public function setSuffix($value)
    {
        $this->suffix = $value;
    }
}
?>