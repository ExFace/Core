<?php
namespace exface\Core\DataTypes;

class DateTimeDataType extends DateDataType
{    
    public static function formatDate(\DateTime $date){
        return $date->format('Y-m-d H:i:s');
    }
}
?>