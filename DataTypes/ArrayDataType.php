<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class ArrayDataType extends AbstractDataType
{
    /**
     * 
     * @param mixed $val
     * @throws DataTypeCastingError
     * @return array
     */
    public static function cast($val)
    {
        if (is_array($val) === false) {
            throw new DataTypeCastingError('Cannot cast ' . gettype($val) . ' to array!');
        }
        return $val;
    }
    
    /**
     * 
     * @param array $array
     * @return bool
     */
    public static function isAssociative(array $array) : bool
    {
        if (array() === $array) return false;
        return array_keys($array) !== range(0, count($array) - 1);
    }
    
    /**
     *
     * @param array $array
     * @return bool
     */
    public static function isSequential(array $array) : bool
    {
        return static::isAssociative($array) === false;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::isValueEmpty()
     */
    public static function isValueEmpty($val) : bool
    {
        return empty($val) === true;
    }
}