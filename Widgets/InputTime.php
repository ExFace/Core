<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\TimeDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Widgets\Traits\SingleValueInputTrait;

/**
 * An input-field for time-values (without date).
 * 
 * The look and handling of the time-input depends on the facade used. It is recommended to 
 * render time spinners (an input with up/down buttons) or other types of selectors: a clock 
 * (like android) or hour/minute sliders (like iOS).
 * 
 * Example:
 * 
 * ```
 *  {
 *      "object_alias": "my.ToDoApp.task",
 *      "attribute_alias": "due_time",
 *      "value": "+1"
 *  }
 *  
 * ```
 * 
 * Supported input formats are:
 * 
 * - hh:mm, hh:mm AM/PM (e.g. "13:59" or "01:59 PM")
 * - hh:mm:ss, hh:mm:ss AM/PM (e.g. "13:59:31" or "01:59:31 PM")
 * - hhmm (eg. 1359)
 * 
 * Shortcut-values (TODO): 
 * 
 * - (+/-)? ... (h/m/s/)? (e.g. 0 => now, 1 or 1h or +1h => in one hour, 45m => in 45 minutes, -15m => 15 minutes ago)
 * 
 * @author Andrej Kabachnik
 */
class InputTime extends Input
{
    use SingleValueInputTrait;
    
    private $showSeconds = null;
    
    private $amPm = null;
    
    private $stepMinutes = 60;
    
    private $format = null;
    
    /**
     * @return string|null
     */
    public function getFormat() : string
    {
        if ($this->format === null) {
            $dataType = $this->getValueDataType();
            if (! $dataType instanceof TimeDataType) {
                $dataType = DataTypeFactory::createFromPrototype($this->getWorkbench(), TimeDataType::class);
            }
            if ($this->showSeconds !== null) {
                $dataType->setShowSeconds($this->showSeconds);
            }
            if ($this->amPm !== null) {
                $dataType->setAmPm($this->amPm);
            }
            $format = $dataType->getFormat();
            $this->format = $format;
        }
        return $this->format;
    }

    
    
    /**
     *
     * @return bool
     */
    public function getShowSeconds() : bool
    {
        if ($this->showSeconds === null) {
            $dataType = $this->getValueDataType();
            if ($dataType instanceof TimeDataType) {
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
     * @return InputTime
     */
    public function setShowSeconds(bool $value) : InputTime
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
        if ($this->amPm === null) {
            $dataType = $this->getValueDataType();
            if ($dataType instanceof TimeDataType) {
                return $dataType->getAmPm();
            } else {
                return false;
            }
        }
        return $this->amPm;
    }
    
    /**
     * Display format for the time
     *
     * @uxon-property format
     * @uxon-type string
     *
     * @param string $format
     * @return InputTime
     */
    public function setFormat($format)
    {
        $this->format = $format;
        return $this;
    }
        
    
    /**
     * Set to TRUE to use the 12-h format with AM/PM.
     *
     * @uxon-property am_pm
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return InputTime
     */
    public function setAmPm(bool $value) : InputTime
    {
        $this->amPm = $value;
        return $this;
    }
    
    /**
     *
     * @return int
     */
    public function getStepMinutes() : int
    {
        return $this->stepMinutes;
    }
    
    /**
     * The step (in minutes) to increment/decrease time if a spinner with buttons is used.
     * 
     * @uxon-property step_minutes
     * @uxon-type integer
     * @uxon-default 60
     * 
     * @param int $value
     * @return InputTime
     */
    public function setStepMinutes(int $value) : InputTime
    {
        $this->stepMinutes = $value;
        return $this;
    }   
}