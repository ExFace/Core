<?php
namespace exface\Core\DataTypes;

class DateTimeDataType extends DateDataType
{   
    
    public static function formatDate(\DateTime $date) : string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
?>