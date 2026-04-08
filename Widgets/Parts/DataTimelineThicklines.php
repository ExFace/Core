<?php

namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;

/**
 * DataTimelineThicklines configuration can set thick lines at specific intervals in a DataTimeline view.
 * 
 * - `interval` - This sets an interval that determines where thick lines are drawn.
 * - `value` - Used with the "week" interval to specify the day of the week for the thick line.
 * - `from` and `to` - Used with the "month_range_in_days" interval to specify the range of days in a month for the thick line.
 * - `color` - sets the color of the thick lines.
 * 
 * ## Examples
 * 
 * Thick lines every week at monday:
 * ```
 *      "thick_lines": {
 *          "interval": "week",
 *          "value": 1,
 *      }
 * ```
 * 
 * Thick lines every month from day 1 to day 7:
 *  ```
 *       "thick_lines": {
 *           "interval": "month_range_in_days",
 *           "from": 1,
 *           "to": 7,
 *       }
 *  ```
 * 
 * Thick lines every start of quarter (Jan/Apr/Jul/Oct):
 *  ```
 *       "thick_lines": {
 *           "interval": "year_quarter",
 *           "color": "#7c7c7c",
 *       }
 *  ```
 * 
 * @author Sergej Riel
 */
class DataTimelineThicklines implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait;
    
    const INTERVAL_WEEK = 'week';
    const INTERVAL_YEAR_QUARTER = 'year_quarter';
    const INTERVAL_MONTH_RANGE_IN_DAYS = 'month_range_in_days';
    
    private $timelineView;
    private ?string $interval = null;
    private ?int $value = null;
    private ?int $from = null;
    private ?int $to = null;
    private ?string $color = null;

    public function __construct(DataTimelineView $timelineView, ?UxonObject $uxon = null)
    {
        $this->timelineView = $timelineView;
        if ($uxon) {
            $this->importUxonObject($uxon);
        }
    }

    public function getWidget(): WidgetInterface
    {
        return $this->timelineView->getWidget();
    }

    /**
     * @inheritDoc
     */
    public function getWorkbench()
    {
        return $this->timelineView->getWorkbench();
    }

    /**
     * @param string|null $default
     * @return string|null
     */
    public function getInterval(string $default = null) : ?string
    {
        return $this->interval;
    }

    /**
     * Sets the interval of the thick line that is used for the chard formats.
     *
     * @uxon-property interval
     * @uxon-type [week,year_quarter,month_range_in_days]
     * @uxon-default week
     *
     * @param string $interval
     * @return $this
     */
    public function setInterval(string $interval) : DataTimelineThicklines
    {
        $this->interval = $this->isValidInterval($interval) ? $interval : null;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getValue() : ?int
    {
        return $this->value;
    }
    
    /**
     * Is used with the combination of interval "week" to set the day of the week.
     * Accepted Values are 0 to 6 (0 = Sunday, 1 = Monday, ..., 6 = Saturday).
     *
     * @uxon-property value
     * @uxon-type int
     *
     * @param int $value
     * @return $this
     */
    public function setValue(int $value) : DataTimelineThicklines
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getFrom() : ?int
    {
        return $this->from;
    }
    
    /**
     * Is used with the combination of interval "month_range_in_days" to set the starting day of the month.
     * Accepted Values are 1 to 31.
     *
     * @uxon-property from
     * @uxon-type int
     *
     * @param int $from
     * @return $this
     */
    public function setFrom(int $from) : DataTimelineThicklines
    {
        $this->from = $from;
        return $this;
    }
    
    /**
     * @return int|null
     */
    public function getTo() : ?int
    {
        return $this->to;
    }
    
    /**
     * Is used with the combination of interval "month_range_in_days" to set the ending day of the month.
     * Accepted Values are 1 to 31.
     *
     * @uxon-property to
     * @uxon-type int
     *
     * @param int $to
     * @return $this
     */
    public function setTo(int $to) : DataTimelineThicklines
    {
        $this->to = $to;
        return $this;
    }
    
    /**
     * @return string|null
     */
    public function getColor() : ?string
    {
        return $this->color;
    }
    
    /**
     * Sets the color of the thick lines.
     *
     * @uxon-property color
     * @uxon-type string
     *
     * @param string $color
     * @return $this
     */
    public function setColor(string $color) : DataTimelineThicklines
    {
        $this->color = $color;
        return $this;
    }
    
    public function isValidInterval(string $interval) : bool
    {
        $const = 'self::INTERVAL_' . mb_strtoupper($interval);
        if (! defined($const)) {
            throw new WidgetConfigurationError($this->getWidget(), 'Invalid interval "' . $interval . '": please use week, year_quarter or month_range_in_days');
        }
        return true;
    }
}