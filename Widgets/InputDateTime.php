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
}