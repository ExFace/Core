<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\RangeDataType;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Widgets\Parts\DateRange;

/**
 * Selects an inclusive range of dates as a single `from..to` value.
 * Both bounds are required. Relative bounds such as `3w..5w` are offsets
 * from today, not calendar week numbers. For date-time attributes, calendar
 * selections include the entire final day. Saved bounds with explicit times
 * keep them, while date-only bounds use the start or end of their day.
 * 
 * For an order-date filter, offer the previous month alongside the calendar:
 * 
 * ```json
 * {
 *   "attribute_alias": "ORDER_DATE",
 *   "input_widget": {
 *     "widget_type": "InputDateRange",
 *     "ranges": [
 *       {"range": "month_previous"}
 *     ]
 *   }
 * }
 * 
 * ```
 * 
 */
class InputDateRange extends InputDate
{
    private bool $allowCustomRange = true;
    private bool $allowDefaultOptions = false;
    private array $ranges = [];
    private ?RangeDataType $rangeDataType = null;

    /**
     * Allow users to select or enter their own dates.
     * This does not disable configured presets with `"range": "custom"`.
     * 
     * @uxon-property allow_custom_range
     * @uxon-type boolean
     * @uxon-default true
     */
    public function setAllowCustomRange(bool $value) : self
    {
        $this->allowCustomRange = $value;
        return $this;
    }

    public function getAllowCustomRange() : bool
    {
        return $this->allowCustomRange;
    }

    /**
     * Named presets offered alongside the calendar, in display order.
     * Custom presets require two valid dates or relative expressions.
     * 
     * @uxon-property ranges
     * @uxon-type \exface\Core\Widgets\Parts\DateRange[]
     * @uxon-template [{"range":"month_previous"},{"caption":"Past 3 months","range":"custom","range_from":"-3m","range_to":0}]
     */
    public function setRanges(UxonObject $ranges) : self
    {
        $parts = [];
        foreach ($ranges as $range) {
            if (! ($range instanceof UxonObject)) {
                throw new WidgetPropertyInvalidValueError($this, 'Each date range preset must be an object.');
            }
            $parts[] = new DateRange($this, $range);
        }
        $this->ranges = $parts;
        return $this;
    }

    /** @return DateRange[] */
    public function getRanges() : array
    {
        return $this->ranges;
    }

    /**
     * Offer the facade's default date range options alongside configured presets.
     * This is independent of allowing users to select or enter their own dates.
     * EasyUI appends the current and previous week and month after configured presets,
     * even if a configured preset covers the same period. UI5 uses its native options.
     * 
     * @uxon-property allow_default_options
     * @uxon-type boolean
     * @uxon-default false
     */
    public function setAllowDefaultOptions(bool $value) : self
    {
        $this->allowDefaultOptions = $value;
        return $this;
    }

    public function getAllowDefaultOptions() : bool
    {
        return $this->allowDefaultOptions;
    }

    /**
     * Returns the closed range type with bounds of the bound date attribute's type.
     */
    public function getValueDataType() : RangeDataType
    {
        if ($this->rangeDataType === null) {
            // Reuse the attribute's date parsing for each endpoint, while
            // keeping the widget's value a single range string.
            $endpointType = parent::getValueDataType();
            if (! ($endpointType instanceof DateDataType)) {
                $endpointType = DataTypeFactory::createFromPrototype($this->getWorkbench(), DateDataType::class);
            }
            $this->rangeDataType = DataTypeFactory::createFromPrototype($this->getWorkbench(), RangeDataType::class)
                ->setEndpointDataType($endpointType);
        }
        return $this->rangeDataType;
    }

    /**
     * Exports the widget configuration, including its range options and presets.
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('allow_custom_range', $this->allowCustomRange);
        $uxon->setProperty('allow_default_options', $this->allowDefaultOptions);
        $uxon->setProperty('ranges', array_map(static fn(DateRange $range) => $range->exportUxonObject()->toArray(), $this->ranges));
        return $uxon;
    }
}
