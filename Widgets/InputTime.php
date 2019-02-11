<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\TimeDataType;

/**
 * An input-field for time-values (without date).
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
 *        
 */
class InputTime extends Input
{
    private $showSeconds = null;
    
    private $amPm = null;
    
    /**
     * @return string|null
     */
    public function getFormat() : string
    {
        $format = $this->getAmPm() ? 'h:i' : 'H:i';
        if ($this->getShowSeconds() === true) {
            $format .= ':s';
        }
        if ($this->getAmPm() === true) {
            $format .= ' A';
        }
        return $format;
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
}
?>