<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;

/**
 * DataTimelineHeader configuration can set different date formats (ICU) for a DataTimeline header.
 * The frist element in the header_lines array is rendered at the top, the next below etc.
 * 
 * - `interval` - This sets an interval that covers a specific time period, such as a month.
 * - `date_format` - sets the date format.
 * - `date_format_at_border` - formats the borders of a chosen interval with different date format.
 * 
 * ## Examples
 * 
 * In frappe-gantt, to show a timeline with months at the top and days below:
 * ```
 *      "header_lines": [
 *          {
 *              "interval": "month",
 *              "date_format": "",
 *              "date_format_at_border": "MMMM"
 *          },
 *          {
 *              "interval": "day",
 *              "date_format": "dd",
 *          }
 *      ]
 * ```
 * 
 * @author Andrej Kabachnik & Sergej Riel
 *
 */
class DataTimelineHeader implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait;
    
    private $timelineView;
    private ?string $interval = DataTimeline::INTERVAL_DAY;
    private ?string $dateFormat = null;
    private ?string $dateFormatAtBorder = null;
    
    
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
     * Sets the interval of the header that is used for the border formats.
     * 
     * @uxon-property interval
     * @uxon-type [day,month,week,year,decade]
     * @uxon-default day
     * 
     * @param string $interval
     * @return $this
     */
    public function setInterval(string $interval) : DataTimelineHeader
    {
        $this->interval = $this->formatInterval($interval);
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDateFormat() : ?string
    {
        return $this->dateFormat;
    }
    
    /**
     * Sets the date format of the header. 
     * Example for 08.02.2025: 
     * - 'd' -> 8,
     * - 'dd' -> 08, 
     * - 'ddd' -> 39 (day of the year)
     * - 'M' -> 2,
     * - 'MM' -> '02', 
     * - 'MMM' -> 'Feb.', 
     * - 'MMMM' -> 'Februar', 
     * - 'yy' -> 25,
     * - 'yyyy' -> 2025
     * 
     * Search for "ICU" to learn more about the format settings.
     * 
     * Special tokens:
     * - '~weekRange': shows the week range from Monday to Sunday. Example: 01.03 - 07.03
     * - '~decade': shows decades. Example: 2020, 2030, ...
     *
     * 
     * @uxon-property date_format
     * @uxon-type string
     * 
     * @param string $value
     * @return $this
     */
    public function setDateFormat(string $value) : DataTimelineHeader
    {
        $this->dateFormat = $value;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDateFormatAtBorder() : ?string
    {
        return $this->dateFormatAtBorder;
    }

    /**
     * Sets the border format of an interval. 
     * Example: If Interval == month -> sets the date format only for the column where the month changes.
     * 
     * For more information, see 'date_format' documentation.
     * 
     * @uxon-property date_format_at_border
     * @uxon-type string
     * 
     * @param string $dateFormatAtBorder
     * @return $this
     */
    public function setDateFormatAtBorder(string $dateFormatAtBorder) : DataTimelineHeader
    {
        $this->dateFormatAtBorder = $dateFormatAtBorder;
        return $this;
    }
    
    private function formatInterval(string $value) : string
    {
        $value = mb_strtolower($value);
        
        // Backwards compatibility with legacy interval types
        switch ($value) {
            case 'day': $value = DataTimeline::INTERVAL_DAY; break;
            case 'month': $value = DataTimeline::INTERVAL_MONTH; break;
            case 'week':  $value = DataTimeline::INTERVAL_WEEK; break;
            case 'year': $value = DataTimeline::INTERVAL_YEAR; break;
            case 'decade': $value = DataTimeline::INTERVAL_DECADE; break;
        }
        
        $const = DataTimeline::class . '::INTERVAL_' . strtoupper($value);
        if (! defined($const)) {
            throw new WidgetConfigurationError($this->getWidget(), 'Invalid timeline header interval "' . $value . '": please use day, month, week or year!');
        }

        return $value;
    }
    
}