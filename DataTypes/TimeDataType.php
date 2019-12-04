<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

class TimeDataType extends AbstractDataType
{    
    private $showSeconds = false;
    
    private $amPm = false;
    
    const TIME_FORMAT_INTERNAL = 'H:i:s';
    
    const TIME_ICU_FORMAT_INTERNAL = 'HH:mm:ss';
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::cast()
     */
    public static function cast($string)
    {
        $string = trim($string);
        
        if ($string === '' || $string === null) {
            return $string;
        }
        
        try {
            $date = new \DateTime($string);
        } catch (\Exception $e) {
            // FIXME add message code with descriptions of valid formats
            throw new DataTypeCastingError('Cannot convert "' . $string . '" to a time!', '74BKHZL', $e);
        }
        return static::formatTimeNormalized($date);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::parse()
     */
    public function parse($value)
    {
        try {
            return parent::parse($value);
        } catch (DataTypeValidationError | DataTypeCastingError $e) {
            $parsed =  $this->getIntlDateFormatter()->parse($value);
            if ($parsed === false) {
                throw $e;
            }
            return $parsed;
        }
    }
    
    /**
     * 
     * @param string $locale
     * @param string $format
     * @return \IntlDateFormatter
     */
    protected static function createIntlDateFormatter(string $locale, string $format) : \IntlDateFormatter
    {
        return new \IntlDateFormatter($locale, NULL, NULL, NULL, NULL, $format);
    }
    
    /**
     * 
     * @return \IntlDateFormatter
     */
    protected function getIntlDateFormatter() : \IntlDateFormatter
    {
        return self::createIntlDateFormatter($this->getLocale(), $this->getFormat());
    }
    
    /**
     * 
     * @return string
     */
    public function getLocale() : string
    {
        return $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
    }
    
    /**
     * 
     * @param \DateTime $date
     * @return string
     */
    public static function formatTimeNormalized(\DateTime $date)
    {    
        return $date->format(self::TIME_FORMAT_INTERNAL);
    }
    
    /**
     * 
     * @param \DateTime $date
     * @return string
     */
    public function formatTime(\DateTime $date) : string
    {
        return $this->getIntlDateFormatter()->format($date);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirectionsDataType::DESC($this->getWorkbench());
    }
    
    /**
     * 
     * @return string
     */
    public function getFormatToParseTo() : string
    {
        return self::TIME_ICU_FORMAT_INTERNAL;
    }
    
    /**
     * 
     * @return string
     */
    public function getFormat() : string
    {
        $format = $this->getAmPm() ? 'hh:mm' : 'HH:mm';
        if ($this->getShowSeconds() === true) {
            $format .= ':ss';
        }
        if ($this->getAmPm() === true) {
            $format .= ' a';
        }
        return $format;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getInputFormatHint()
     */
    public function getInputFormatHint() : string
    {
        return $this->getApp()->getTranslator()->translate('LOCALIZATION.DATE.TIME_FORMAT_HINT');
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
     * Set to TRUE to show the seconds.
     * 
     * @uxon-property show_seconds
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return TimeDataType
     */
    public function setShowSeconds(bool $value) : TimeDataType
    {
        $this->showSeconds = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getAmPm() : bool
    {
        return $this->amPm;
    }
    
    /**
     * Set to TRUE to use the 12-h format with AM/PM.
     * 
     * @uxon-property am_pm
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return TimeDataType
     */
    public function setAmPm(bool $value) : TimeDataType
    {
        $this->amPm = $value;
        return $this;
    }
    
}