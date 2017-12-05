<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumDynamicDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * String enumeration - list of allowed text values.
 * 
 * @author Andrej Kabachnik
 *
 */
class StringEnumDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumDynamicDataTypeTrait;
}
?>