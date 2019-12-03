<?php
namespace exface\Core\DataTypes;

class TimestampDataType extends DateDataType
{    
    /*public static function formatDate(\DateTime $date) : string
    {
        return $date->format(DateTimeDataType::DATETIME_FORMAT_INTERNAL);
    }*/
    
    public function getFormatToParseTo() : string
    {
        return DateTimeDataType::DATE_ICU_FORMAT_INTERNAL;
    }
    
    public function getFormat() : string
    {
        return $this->hasCustomFormat() ? $this->getFormat() : $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT');
    }
}
?>