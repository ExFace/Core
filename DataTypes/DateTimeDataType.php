<?php
namespace exface\Core\DataTypes;

class DateTimeDataType extends DateDataType
{   
    const DATETIME_FORMAT_INTERNAL = 'Y-m-d H:i:s';
    
    const DATETIME_ICU_FORMAT_INTERNAL = 'yyyy-MM-dd HH:mm:ss';
    
    public static function formatDateNormalized(\DateTime $date) : string
    {
        return $date->format(self::DATETIME_FORMAT_INTERNAL);
    }
    
    public function getFormatToParseTo() : string
    {
        return self::DATETIME_ICU_FORMAT_INTERNAL;
    }
    
    public function getFormat() : string
    {
        return $this->hasCustomFormat() ? $this->getFormat() : $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT');
    }
}
?>