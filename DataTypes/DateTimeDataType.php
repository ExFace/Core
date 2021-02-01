<?php
namespace exface\Core\DataTypes;

use exface\Core\Interfaces\WorkbenchInterface;

class DateTimeDataType extends DateDataType
{   
    const DATETIME_FORMAT_INTERNAL = 'Y-m-d H:i:s';
    
    const DATETIME_ICU_FORMAT_INTERNAL = 'yyyy-MM-dd HH:mm:ss';
    
    /**
     * 
     * @param \DateTime $date
     * @return string
     */
    public static function formatDateNormalized(\DateTime $date) : string
    {
        return $date->format(self::DATETIME_FORMAT_INTERNAL);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\DateDataType::getFormatToParseTo()
     */
    public function getFormatToParseTo() : string
    {
        return self::DATETIME_ICU_FORMAT_INTERNAL;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\DateDataType::getFormat()
     */
    public function getFormat() : string
    {
        return $this->hasCustomFormat() ? parent::getFormat() : static::getFormatForCurrentTranslation($this->getWorkbench());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\DateDataType::getInputFormatHint()
     */
    public function getInputFormatHint() : string
    {
        return $this->getApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT_HINT');
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string
     */
    protected static function getFormatForCurrentTranslation(WorkbenchInterface $workbench) : string
    {
        return $workbench->getCoreApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT');
    }
}
?>