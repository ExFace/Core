<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\Traits\DataWidgetPartTrait;

/**
 * DataTimeline configuration for data-widgets like Scheduler or certain Chart types.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataTimeline implements WidgetPartInterface
{
    use DataWidgetPartTrait;
    
    const GRANULARITY_DAY = 'day';
    const GRANULARITY_HOUR = 'hour';
    const GRANULARITY_WEEK = 'week';
    const GRANULARITY_MONTH = 'month';
    
    private $granularity = null;
    
    private $workday_start_time = null;
    
    private $workday_end_time = null;
    
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            'start_time' => $this->getStartTimeColumn()->getAttributeAlias(),
            'granularity' => $this->getGranularity()
        ]);
        
        if ($this->hasEndTimeColumn()) {
            $uxon->setProperty('end_time', $this->getEndTimeColumn()->getAttributeAlias());
        }
        
        return $uxon;
    }
    
    /**
     *
     * @return string
     */
    public function getGranularity(string $default = null) : ?string
    {
        return $this->granularity ?? $default;
    }
    
    /**
     * 
     * @param string $value
     * @return DataTimeline
     */
    public function setGranularity(string $value) : DataTimeline
    {
        $this->granularity = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getWorkdayStartTime() : ?string
    {
        if ($this->workday_start_time === null) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            if ($translator->hasTranslation('LOCALIZATION.CALENDAR.WORKDAY_START_TIME')) {
                $this->workday_start_time = $translator->translate('LOCALIZATION.CALENDAR.WORKDAY_START_TIME');
            }
        }
        return $this->workday_start_time;
    }
    
    /**
     * 
     * @param string $value
     * @return DataTimeline
     */
    public function setWorkdayStartTime(string $value) : DataTimeline
    {
        $this->workday_start_time = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getWorkdayEndTime() : ?string
    {
        if ($this->workday_end_time === null) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            if ($translator->hasTranslation('LOCALIZATION.CALENDAR.WORKDAY_END_TIME')) {
                $this->workday_end_time = $translator->translate('LOCALIZATION.CALENDAR.WORKDAY_END_TIME');
            }
        }
        return $this->workday_end_time;
    }
    
    /**
     *
     * @param string $value
     * @return DataTimeline
     */
    public function setWorkdayEndTime(string $value) : DataTimeline
    {
        $this->workday_end_time = $value;
        return $this;
    }
}