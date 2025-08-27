<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateTimeDataType;

/**
 * An date-input incl. time.
 * 
 * @author Andrej Kabachnik
 *
 */
class InputDateTime extends InputDate
{
    private $showSeconds = null;
    private $defaultTime = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputDate::getFormatDefault()
     */
    protected function getFormatDefault() : string
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), DateTimeDataType::class)->getFormat();
    }
    
    /**
     *
     * @return bool
     */
    public function getShowSeconds() : bool
    {
        if ($this->showSeconds === null) {
            $dataType = $this->getValueDataType();
            if ($dataType instanceof DateTimeDataType) {
                return $dataType->getShowSeconds();
            } else {
                return false;
            }
        }
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
     * @return InputDateTime
     */
    public function setShowSeconds(bool $value) : InputDateTime
    {
        $this->showSeconds = $value;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDefaultTime() : ?string
    {
        return $this->defaultTime;
    }

    /**
     * Sets the default time (hh.mm) for the date-time picker.
     * If this property is not set, the current time is used instead.
     * Example: "12:00"
     *
     * @uxon-property default_time
     * @uxon-type string
     * @uxon-default "12:00"
     *
     * @param string $time
     * @return InputDateTime
     */
    public function setDefaultTime(string $time) : InputDateTime
    {
        $this->defaultTime = $time;
        return $this;
    }
}