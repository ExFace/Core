<?php
namespace exface\Core\DataTypes;

class BooleanDataType extends AbstractDataType
{

    public static function cast($string)
    {
        if (is_null($string) || strcasecmp($string, EXF_LOGICAL_NULL) === 0){
            return null;
        }
        return filter_var($string, FILTER_VALIDATE_BOOLEAN);
    }
}
?>