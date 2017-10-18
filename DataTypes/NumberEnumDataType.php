<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumDataTypeTrait;

/**
 * Enumeration - list of allowed values.
 * 
 * @author Andrej Kabachnik
 *
 */
class NumberEnumDataType extends NumberDataType
{
    use EnumDataTypeTrait;
}
?>