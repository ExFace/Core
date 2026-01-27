<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\WidgetDimension;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\Traits\iHaveIconTrait;

/**
 * DataTimelineView configuration can set different chard "views" that changes the header and the chard layout.
 * 
 * Widgets like the Gantt chart automatically adds the corresponding buttons for switching views to the toolbar.
 * 
 * ## Examples
 *
 *  ```
 *  "views": [
 *        {
 *            "name": "Tage",
 *            "description": "Tagesansicht",
 *            "granularity": "days",
 *            "date_format": "YYYY-MM-dd",
 *            "padding": "7d",
 *            "header_lines": [ ... ],
 *            "thick_lines": [ ... ],
 *         },
 *        {
 *           "name": "Wochen",
 *           "description": "Wochenansicht",
 *           "granularity": "weeks",
 *           "date_format": "YYYY-MM-dd",
 *           "column_width": 140,
 *           "padding": "1m",
 *           "upper_text_frequency": 4,
 *           "header_lines": [ ... ],
 *           "thick_lines": [ ... ],
 *       },
 *       {
 *           "name": "Monate",
 *           "description": "Monatsansicht",
 *           "granularity": "months",
 *           "date_format": "YYYY-MM",
 *           "column_width": 120,
 *           "padding": "2m",
 *           "snap_at": "weekly",
 *           "header_lines": [ ... ],
 *           "thick_lines": [ ... ],
 *       }
 *  ]
 *  ```
 * 
 * 
 * @author Andrej Kabachnik & Sergej Riel
 *
 */
class DataTimelineView implements WidgetPartInterface, iHaveIcon
{
    use ICanBeConvertedToUxonTrait;
    
    use iHaveIconTrait;
    
    const GRANULARITY_DAYS = 'days';
    const GRANULARITY_QUARTER_DAYS  = 'quarter_days';
    const GRANULARITY_HALF_DAYS  = 'half_days';
    const GRANULARITY_DAYS_PER_WEEK = 'days_per_week';
    const GRANULARITY_DAYS_PER_MONTH = 'days_per_month';
    const GRANULARITY_HOURS = 'hours';
    const GRANULARITY_WEEKS = 'weeks';
    const GRANULARITY_MONTHS = 'months';
    const GRANULARITY_YEARS = 'years';
    const SNAP_AT_DAILY = 'daily';
    const SNAP_AT_WEEKLY = 'weekly';
    const SNAP_AT_MONTHLY = 'monthly';
    
    
    private $timeline;
    private ?string $name = null;
    private ?string $description = null;
    private $granularity = null;
    private ?string $date_format = null;
    private ?string $padding = null;
    private ?string $snap_at = null;
    private ?int $upper_text_frequency = null;
    private ?WidgetDimension $columnWidth = null;
    private ?array $headerLines = null;
    private ?UxonObject $headerLinesUxon = null;
    private ?array $thick_lines = null;
    private ?UxonObject $thickLinesUxon = null;
    
    public function __construct(DataTimeline $timeline, ?UxonObject $uxon = null)
    {
        $this->timeline = $timeline;
        if ($uxon) {
            $this->importUxonObject($uxon);
        }
    }

    public function getWidget(): WidgetInterface
    {
        return $this->timeline->getWidget();
    }

    /**
     * @inheritDoc
     */
    public function getWorkbench()
    {
        return $this->timeline->getWorkbench();
    }

    /**
     * @return string|null
     */
    public function getName() : ?string
    {
        return $this->name;
    }

    /**
     * Sets the name of the timeline view
     * 
     * @uxon-property name
     * @uxon-type string
     * 
     * @param string $name
     * @return $this
     */
    public function setName(string $name): DataTimelineView
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription() : ?string
    {
        return $this->description;
    }

    /**
     * Sets the description of the timeline view
     * 
     * @uxon-property description
     * @uxon-type string
     * 
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description): DataTimelineView
    {
        $this->description = $description;
        return $this;
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
     * Granularity is the smallest time unit visible (e.g. days)
     *
     * @uxon-property granularity
     * @uxon-type [hours,days,weeks,months,years]
     * @uxon-default hour
     *
     * @param string $value
     * @return DataTimelineView
     */
    public function setGranularity(string $value) : DataTimelineView
    {
        $this->granularity = $this->formatGranularity($value);
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDateFormat() : ?string
    {
        return $this->date_format;
    }
    
    /**
     * Sets the date format of the timeline view.
     * Example: "YYYY-MM-dd"
     * 
     * @uxon-property date_format
     * @uxon-type string
     * 
     * @param string $dateFormat
     * @return $this
     */
    public function setDateFormat(string $dateFormat) : DataTimelineView
    {
        $this->date_format = $dateFormat;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPadding() : ?string
    {
        return $this->padding;
    }
    
    /**
     * Sets the padding of the timeline view.
     * In frappe-gantt, this is the extra space before the start of the first task and after the end date of the last task inside the timeline.
     * Examples: 
     * - "7d" adds 7 days of padding on both sides.
     * - "1m" adds 1 month of padding on both sides.
     * - "2y" adds 2 years of padding on both sides.
     * 
     * @uxon-property padding
     * @uxon-type string
     * 
     * @param string $padding
     * @return $this
     */
    public function setPadding(string $padding) : DataTimelineView
    {
        $this->padding = $padding;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSnapAt() : ?string
    {
        return $this->snap_at;
    }
    
    /**
     * Sets the snap_at of the timeline view.
     * In frappe-gantt, this defines the snapping behavior when dragging tasks.
     * Possible values:
     * - "daily" (snaps to each day)
     * - "weekly" (snaps to each week)
     * - "monthly" (snaps to each month)
     * 
     * @uxon-property snap_at
     * @uxon-type [daily,weekly,monthly]
     * 
     * @param string $snapAt
     * @return $this
     */
    public function setSnapAt(string $snapAt) : DataTimelineView
    {
        $this->snap_at = $this->isValidSnapAt($snapAt) ? $snapAt : null;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getUpperTextFrequency() : ?int
    {
        return $this->upper_text_frequency;
    }
    
    /**
     * Sets the upper_text_frequency of the timeline view.
     * In frappe-gantt, this defines how often the upper header text is displayed.
     * For example, a value of 4 means the upper header text is shown every 4 units of the granularity.
     * 
     * @uxon-property upper_text_frequency
     * @uxon-type integer
     * 
     * @param int $frequency
     * @return $this
     */
    public function setUpperTextFrequency(int $frequency) : DataTimelineView
    {
        $this->upper_text_frequency = $frequency;
        return $this;
    }
    
    /**
     * @return WidgetDimension
     */
    public function getColumnWidth() : ?WidgetDimension
    {
        return $this->columnWidth;
    }

    /**
     * Width of the smallest visible columns (= granularity)
     * 
     * @uxon-property column_width
     * @uxon-type string
     * 
     * @param string $width
     * @return $this
     */
    public function setColumnWidth(string $width) : DataTimelineView
    {
        $this->columnWidth = new WidgetDimension($this->getWorkbench(), $width);
        return $this;
    }

    /**
     * It gets the header lines with its settings.
     * 
     * @return array
     */
    public function getHeaderLines() : ?array
    {
       if ($this->headerLines === null) {
           foreach ($this->headerLinesUxon as $uxon) {
               $this->headerLines[] = new DataTimelineHeader($this, $uxon);
           }
       } 
       return $this->headerLines;
    }
    
    /**
     * Header lines settings
     * 
     * @uxon-property header_lines
     * @uxon-type \exface\Core\Widgets\Parts\DataTimelineHeader[]
     * @uxon-template [{"interval": "month", "date_format": "d", "date_format_at_border": "d MMM"}]
     * 
     * @param UxonObject $arrayOfHeaderLines
     * @return $this
     */
    protected function setHeaderLines(UxonObject $arrayOfHeaderLines) : DataTimelineView
    {
        $this->headerLinesUxon = $arrayOfHeaderLines;
        $this->headerLines = null;
        return $this;
    }

    /**
     * It gets the thick lines with its settings.
     *
     * @return array|null
     */
    public function getThickLines() : ?array
    {
       if ($this->thick_lines === null) {
           foreach ($this->thickLinesUxon as $uxon) {
               $this->thick_lines[] = new DataTimelineThicklines($this, $uxon);
           }
       } 
       return $this->thick_lines;
    }
    
    /**
     * Thick lines settings
     *
     * @uxon-property thick_lines
     * @uxon-type \exface\Core\Widgets\Parts\DataTimelineThicklines[]
     * @uxon-template [{"interval": "week", "value": 1}]
     *
     * @param UxonObject $arrayOfThickLines
     * @return $this
     */
    protected function setThickLines(UxonObject $arrayOfThickLines) : DataTimelineView
    {
        $this->thickLinesUxon = $arrayOfThickLines;
        $this->thick_lines = null;
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
    
    public function isValidSnapAt(string $snap_at) : bool
    {
        $const = 'self::SNAP_AT_' . mb_strtoupper($snap_at);
        if (! defined($const)) {
            throw new WidgetConfigurationError($this->getWidget(), 'Invalid snap_at value: "' . $snap_at . '": please use daily, weekly,or monthly!');
        }
        return true;
    }
}