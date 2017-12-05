<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumDynamicDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Numeric enumeration - list of allowed numeric values.
 * 
 * @author Andrej Kabachnik
 *
 */
class NumberEnumDataType extends NumberDataType implements EnumDataTypeInterface
{
    use EnumDynamicDataTypeTrait;
}
?>