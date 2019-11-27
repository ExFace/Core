<?php
namespace exface\Core\DataTypes;

class DateTimeDataType extends DateDataType
{   
    const DATETIME_FORMAT_INTERNAL = 'Y-m-d H:i:s';
    
    public static function formatDate(\DateTime $date) : string
    {
        return $date->format(self::DATETIME_FORMAT_INTERNAL);
    }
    
    public function getFormatToParseTo() : string
    {
        return self::DATETIME_FORMAT_INTERNAL;
    }
    
    public function getFormat() : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT');
    }
}
?>