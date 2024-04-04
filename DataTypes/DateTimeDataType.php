<?php
namespace exface\Core\DataTypes;

use exface\Core\Interfaces\WorkbenchInterface;

/**
 * A date with time
 * 
 * ## Time zone handling
 * 
 * The system assumes
 * 
 * @author andrej.kabachnik
 *
 */
class DateTimeDataType extends DateDataType
{   
    const DATETIME_FORMAT_INTERNAL = 'Y-m-d H:i:s';
    
    const DATETIME_ICU_FORMAT_INTERNAL = 'yyyy-MM-dd HH:mm:ss';
    
    private $showSeconds = false;
    
    private $showMilliseconds = false;
    
    private $timeZoneDependent = true;
    
    /**
     * 
     * @param \DateTime $date
     * @return string
     */
    public static function formatDateNormalized(\DateTimeInterface $date) : string
    {
        return $date->format(self::DATETIME_FORMAT_INTERNAL);
    }
    
    /**
     * 
     * @param \DateTimeInterface $dateTime
     * @param bool $returnPhpDate
     * @return \DateTimeInterface|string
     */
    public static function castFromPhpDate(\DateTimeInterface $dateTime, bool $returnPhpDate = false)
    {
        return $returnPhpDate ? $dateTime : static::formatDateNormalized($dateTime);
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
        return $this->hasCustomFormat() ? parent::getFormat() : static::getFormatFromLocale($this->getWorkbench(), $this->getShowSeconds());
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
    public static function getFormatFromLocale(WorkbenchInterface $workbench, bool $withSeconds = false) : string
    {
        return $workbench->getCoreApp()->getTranslator()->translate($withSeconds ? 'LOCALIZATION.DATE.DATETIME_FORMAT_WITH_SECONDS' : 'LOCALIZATION.DATE.DATETIME_FORMAT');
    }
    
    /**
     *
     * @return bool
     */
    public function getShowSeconds() : bool
    {
        return $this->showSeconds;
    }
    
    /**
     * Set to TRUE to show the seconds (has no effect when custom `format` specified!).
     *
     * @uxon-property show_seconds
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return TimeDataType
     */
    public function setShowSeconds(bool $value) : DateTimeDataType
    {
        $this->showSeconds = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getShowMilliseconds() : bool
    {
        return $this->showMilliseconds;
    }
    
    /**
     * Set to TRUE to show use time with milliseconds (has no effect when custom `format` specified!).
     *
     * @uxon-property show_milliseconds
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return TimeDataType
     */
    public function setShowMilliseconds(bool $value) : DateTimeDataType
    {
        $this->showMilliseconds = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isTimeZoneDependent() : bool
    {
        return $this->timeZoneDependent;
    }
    
    /**
     * Set to FALSE to make values of this type ignore time zones completely
     *
     * @uxon-property time_zone_dependent
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $value
     * @return TimeDataType
     */
    public function setTimeZoneDependent(bool $value) : TimeDataType
    {
        $this->timeZoneDependent = $value;
        return $this;
    }
}