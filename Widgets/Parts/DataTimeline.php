<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\Traits\DataWidgetPartTrait;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * DataTimeline configuration for data-widgets like Scheduler or certain Chart types.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataTimeline implements WidgetPartInterface
{
    use DataWidgetPartTrait;
    
    const GRANULARITY_DAYS = 'days';
    const GRANULARITY_DAYS_PER_WEEK = 'days_per_week';
    const GRANULARITY_DAYS_PER_MONTH = 'days_per_month';
    const GRANULARITY_HOURS = 'hours';
    const GRANULARITY_WEEKS = 'weeks';
    const GRANULARITY_MONTHS = 'months';
    const GRANULARITY_YEARS = 'years';
    
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
     * Initial zoom level: hours, days, weeks or months
     * 
     * @uxon-property granularity
     * @uxon-type [hours,days,days_per_week,days_per_month,weeks,months]
     * @uxon-default hour
     * 
     * @param string $value
     * @return DataTimeline
     */
    public function setGranularity(string $value) : DataTimeline
    {
        $value = mb_strtolower($value);
        
        // Backwards compatibility with legacy granularity types
        switch ($value) {
            case 'hour': $value = self::GRANULARITY_HOURS; break;
            case 'day': $value = self::GRANULARITY_DAYS; break;
            case 'week': $value = self::GRANULARITY_DAYS_PER_WEEK; break;
            case 'month': $value = self::GRANULARITY_DAYS_PER_MONTH; break;
        }
        
        $const = DataTimeline::class . '::GRANULARITY_' . strtoupper($value);
        if (! defined($const)) {
            throw new WidgetConfigurationError($this->getWidget(), 'Invalid timeline granularity "' . $value . '": please use hours, days, days_per_week, days_per_month, weeks or months!');
        }
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
     * Start of business hours - e.g. `8:00`.
     * 
     * @uxon-property workday_start_time
     * @uxon-type time
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
     * End of business hours - e.g. `18:00`.
     * 
     * @uxon-property workday_end_time
     * @uxon-type time
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