<?php
namespace exface\Core\DataTypes;

class TimestampDataType extends DateDataType
{    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\DateDataType::formatDateNormalized()
     */
    public static function formatDateNormalized(\DateTime $date) : string
    {
        return $date->format(DateTimeDataType::DATETIME_FORMAT_INTERNAL);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\DateDataType::getFormatToParseTo()
     */
    public function getFormatToParseTo() : string
    {
        return DateTimeDataType::DATETIME_ICU_FORMAT_INTERNAL;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\DateDataType::getFormat()
     */
    public function getFormat() : string
    {
        return $this->hasCustomFormat() ? parent::getFormat() : $this->getWorkbench()->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT');
    }
}
?>