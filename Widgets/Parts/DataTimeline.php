<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\Traits\DataWidgetPartTrait;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * DataTimeline configuration for data-widgets like Scheduler or certain Chart types.
 * 
 * This is the configuration for the timeline header in widgets like Scheduler, Gantt, etc. Each of these
 * widgets allows the user to switch "views" - e.g. "days per month", "months per year", etc.
 * 
 * You can control, which views are available and even customize these views by setting their 
 * - `granularity` - smallest time unit visible (e.g. days)
 * - `column_width` - visual width of one time unit item (e.g. how wide is one day)
 * - visible `interval` - e.g. quarter to show exactly one quarter with day or week granularity
 * 
 * ## Examples
 * 
 * ```
 * {
 *      "timeline": {
 *          "views": [
 *              {
 *                  "name": "Days",
 *                  "icon": "", //TODO SR: Build it?
 *                  "description": "Show as many days as there is space on the screen"
 *                  "granularity": "days",
 *                  "column_width": "38",
 *                  "highlight_weekends": true //TODO SR: Build it?
 *              },
 *              {
 *                  "name": "Week per year",
 *                  "description": "Show the weeks of exactly one year"
 *                  "granularity": "weeks",
 *                  "visible_interval": "year" //TODO SR: Build it? Should the Gantt chard only show the current year and cut of the rest?
 *                  "column_width": "70",
 *                  "header_lines": [
 *                      {
 *                          "interval": "year",
 *                          "format": "YYYY"
 *                      },
 *                      {
 *                          "interval": "Year",
 *                          "format": "ww"
 *                      }
 *                  ]
 *              },
 *              {
 *                  "name": "Super cool custom view",
 *                  "description": "Show days grouped by week with highlighted weekends"
 *                  "granularity": "weeks",
 *                  "bordered_interval": "month", //TODO SR: Build it? => It got solved with header_lines.interval
 *                  "border_color": "darkgray", //TODO SR: Build it?
 *                  "column_width": "40"
 *              },
 *          ]
 *      }    
 * }
 * 
 * ```
 *
 * ```
 * {
 *      "timeline": {
 *          "granularity": "days",
 *          "highlight_weekends": true,
 *          "views": [
 *              {
 *                  "name": "Days",
 *                  "description": "Show as many days as there is space on the screen"
 *                  "column_width": "38"
 *              },
 *              {
 *                  "name": "Days per week",
 *                  "description": "Show the days of exactly one week"
 *                  "interval": "week"
 *              },
 *              {
 *                  "name": "All",
 *                  "description": "Show the entire project timeline in months"
 *                  "interval": "month",
 *                  "visible_interval": "all"
 *              },
 *          ]
 *      }
 * }
 *
 * ```
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
    const GRANULARITY_ALL = 'all';
    
    const INTERVAL_DAY = 'day';
    const INTERVAL_WEEK = 'week';
    const INTERVAL_MONTH = 'month';
    const INTERVAL_YEAR = 'year';
    
    private ?array $views = null;
    private ?UxonObject $viewsUxon = null;
    
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
     * Initial zoom level: `hours`, `days`, `weeks`, `months`, `years` or `all`
     * 
     * @uxon-property granularity
     * @uxon-type [hours,days,days_per_week,days_per_month,weeks,months,years,all]
     * @uxon-default hour
     * 
     * @param string $value
     * @return DataTimeline
     */
    public function setGranularity(string $value) : DataTimeline
    {
        $this->granularity = $this->formatGranularity($value);
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
    
    private function formatGranularity(string $value) : string
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
        
        return $value;
    }

    /**
     * It gets the views with its settings. The 'days', 'weeks' and 'months' views are default.
     * 
     * @return DataTimelineView[]
     */
    public function getViews() : array
    {
        if ($this->views === null) {
            if ($this->viewsUxon === null) {
                $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
                // IDEA get defaults from widget? Different defaults for Gantt and Scheduler?
                $this->views = [
                    new DataTimelineView($this, new UxonObject([
                        'name' => $translator->translate('WIDGET.GANTT_CHARD.VIEW_MODE_DAY'),
                        'granularity' => DataTimelineView::GRANULARITY_DAYS,
                        'description' => $translator->translate('WIDGET.GANTT_CHARD.VIEW_MODE_DAY_DESCRIPTION'),
                        'column_width' => 38
                    ])),
                    new DataTimelineView($this, new UxonObject([
                        'name' => $translator->translate('WIDGET.GANTT_CHARD.VIEW_MODE_WEEK'),
                        'granularity' => DataTimelineView::GRANULARITY_WEEKS,
                        'description' => $translator->translate('WIDGET.GANTT_CHARD.VIEW_MODE_WEEK_DESCRIPTION'),
                        'column_width' => 140
                    ])),
                    new DataTimelineView($this, new UxonObject([
                        'name' => $translator->translate('WIDGET.GANTT_CHARD.VIEW_MODE_MONTH'),
                        'granularity' => DataTimelineView::GRANULARITY_MONTHS,
                        'description' => $translator->translate('WIDGET.GANTT_CHARD.VIEW_MODE_MONTH_DESCRIPTION'),
                        'column_width' => 20
                    ])),
                ];
            } else {
                foreach ($this->viewsUxon as $uxon) {
                    if ($this->granularity !== null && ! $uxon->hasProperty('granularity')) {
                        $uxon->setProperty('granularity', $this->granularity);
                    }
                    $this->views[] = new DataTimelineView($this, $uxon);
                }
            }
        }
        return $this->views;
    }
    
    /**
     * Different views (zoom levels) the user can select
     * 
     *  You can control, which views are available and even customize these views by setting their
     *  - `granularity` - smallest time unit visible (e.g. days)
     *  - `column_width` - visual width of one time unit item (e.g. how wide is one day)
     * 
     * @uxon-property views
     * @uxon-type \exface\Core\Widgets\Parts\DataTimelineView[]
     * @uxon-template [{"name": "", "description": "", "granularity": ""}]
     * 
     * @param UxonObject $arrayOfViews
     * @return $this
     */
    protected function setViews(UxonObject $arrayOfViews) : DataTimeline
    {
        $this->viewsUxon = $arrayOfViews;
        $this->views = null;
        return $this;
    }
    
    public function isValidInterval(string $interval) : bool
    {
        $const = 'self::INTERVAL_' . mb_strtoupper($interval);
        if (! defined($const)) {
            throw new WidgetConfigurationError($this->getWidget(), 'Invalid interval "' . $interval . '": please use hour, day, days_per_week, week or month!');
        }
        return true;
    }
}