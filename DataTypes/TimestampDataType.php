<?php
namespace exface\Core\DataTypes;

class TimestampDataType extends DateDataType
{    
    public static function formatDate(\DateTime $date) : string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
?>