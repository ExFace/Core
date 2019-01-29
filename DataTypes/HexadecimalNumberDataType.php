<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

/**
 * Data type for Hexadecimal numbers.
 * 
 * @author Andrej Kabachnik
 *
 */
class HexadecimalNumberDataType extends NumberDataType
{
    
    /**
     *
     * {@inheritdoc}
     * @see NumberDataType::cast()
     */
    public static function cast($string)
    {
        if (static::isEmptyValue($string) === true) {
            // Return NULL for casting empty values as an empty string '' actually is not a number!
            return null;
        } elseif (mb_strtoupper(substr($string, 0, 2)) === '0X') {
            // Hexadecimal numbers in '0x....'-Notation
            /*if (ctype_xdigit(substr($string, 2)) === false) {
                throw new DataTypeCastingError('Cannot convert "' . $string . '" to a hexadecimal number!');
            }*/
            return $string;
        } else {
            throw new DataTypeCastingError('Cannot convert "' . $string . '" to a hexadecimal number!');
            return '';
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
}
?>