<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;

class BooleanDataType extends AbstractDataType
{

    public static function cast($string)
    {
        if ($string === null || strcasecmp($string, EXF_LOGICAL_NULL) === 0){
            return null;
        }
        return filter_var($string, FILTER_VALIDATE_BOOLEAN);
    }
}
?>