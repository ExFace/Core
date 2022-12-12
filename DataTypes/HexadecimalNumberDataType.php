<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

/**
 * Data type for Hexadecimal numbers.
 * 
 * @author Andrej Kabachnik
 *
 */
class HexadecimalNumberDataType extends NumberDataType
{
    const HEX_PREFIX = '0x';
    
    /**
     *
     * {@inheritdoc}
     * @see NumberDataType::cast()
     */
    public static function cast($string)
    {
        if (is_string($string) === false && $string !== null) {
            throw new DataTypeCastingError('Cannot cast "' . gettype($string) . '" to a hexadecimal number');
        }
        switch (true) {
            // Return NULL for casting empty values as an empty string '' actually is not a number!
            case static::isValueEmpty($string) === true:
                return null;
            // Hexadecimal numbers in '0x....'-Notation
            case stripos($string, self::HEX_PREFIX) === 0:
                if (ctype_xdigit(substr($string, 2)) === false) {
                    throw new DataTypeCastingError('Cannot convert "' . $string . '" to a hexadecimal number!');
                }
                return $string;
            // Logical NULL
            case static::isValueLogicalNull($string):
                return $string;
            default: 
                throw new DataTypeCastingError('Cannot convert "' . (strlen($string) > 30 ? substr($string, 0, 30) . '...' : $string) . '" to a hexadecimal number!');
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\NumberDataType::parse()
     */
    public function parse($string)
    {
        try {
            $number = parent::parse($string);
        } catch (\Throwable $e) {
            throw $this->createValidationError($e->getMessage(), $e->getCode(), $e);
        }
        
        /* TODO
        if (! is_null($this->getMin()) && $number < $this->getMin()) {
            throw new DataTypeValidationError($this, $number . ' is less than the minimum of ' . $this->getMin() . ' allowed for data type ' . $this->getAliasWithNamespace() . '!');
        }
        
        if (! is_null($this->getMax()) && $number > $this->getMax()) {
            throw new DataTypeValidationError($this, $number . ' is greater than the maximum of ' . $this->getMax() . ' allowed for data type ' . $this->getAliasWithNamespace() . '!');
        }*/
        
        return $number;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\NumberDataType::getBase()
     */
    public function getBase()
    {
        return 16;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see NumberDataType::format()
     */
    public function format($value = null) : string
    {
        $val = $value !== null ? $this->parse($value) : $this->getValue();
        if ($val === null || $val === '' || $val === EXF_LOGICAL_NULL) {
            return $this->getEmptyFormat();
        }
        return $val;
    }
}