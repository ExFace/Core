<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

/**
 * Data type for empty values (e.g. for attributes, that cannot contain any value)
 * 
 * @author Andrej Kabachnik
 *
 */
class EmptyDataType extends AbstractDataType
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::cast()
     */
    public static function cast($string)
    {
        if (static::isValueEmpty($string)) {
            return null;
        }
        throw new DataTypeCastingError('Cannot cast value "' . $string . '" to empty data type!');
    }
}