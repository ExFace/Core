<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;

/**
 * DataTimelineHeader configuration can set different date formats (ICU) for a DataTimeline header.
 * 
 * - `interval` - This sets an interval that covers a specific time period, such as a month.
 * - `date_format` - sets the date format.
 * - `date_format_at_border` - formats the borders of a chosen interval with different date format.
 * 
 * ## Examples
 * ```
 *      "header_lines": [
 *          {
 *              "interval": "month",
 *              "date_format": "",
 *              "date_format_at_border": "MMM"
 *          },
 *          {
 *              "interval": "month",
 *              "date_format": "d",
 *              "date_format_at_border": "d MMM"
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
     * @uxon-type [day,month,week,year]
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
     * Example for 17.12.2025: 'M' -> '12' and 'MMM' -> 'Dez.'.
     * 
     * Search for "ICU" to learn more about the format settings.
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
            case 'weer':  $value = DataTimeline::INTERVAL_WEEK; break;
            case 'year': $value = DataTimeline::INTERVAL_YEAR; break;
        }
        
        $const = DataTimeline::class . '::INTERVAL_' . strtoupper($value);
        if (! defined($const)) {
            throw new WidgetConfigurationError($this->getWidget(), 'Invalid timeline header interval "' . $value . '": please use day, month, week or year!');
        }

        return $value;
    }
    
}