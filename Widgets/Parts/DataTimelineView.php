<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\WidgetDimension;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\Traits\DataWidgetPartTrait;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class DataTimelineView implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait;
    
    const GRANULARITY_DAYS = 'days';
    const GRANULARITY_DAYS_PER_WEEK = 'days_per_week';
    const GRANULARITY_DAYS_PER_MONTH = 'days_per_month';
    const GRANULARITY_HOURS = 'hours';
    const GRANULARITY_WEEKS = 'weeks';
    const GRANULARITY_MONTHS = 'months';
    const GRANULARITY_YEARS = 'years';
    
    private $timeline;
    private ?string $name = null;
    private ?string $description = null;
    private $granularity = null;
    private ?WidgetDimension $columnWidth = null;
    
    // TODO: Build these:
    private ?array $headerLines = null;
    private ?UxonObject $headerLinesUxon = null;
    
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
}