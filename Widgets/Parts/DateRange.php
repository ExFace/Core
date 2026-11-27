<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\InputDateRange;

/**
 * A selectable preset for an InputDateRange.
 * 
 * Add this preset to `ranges` to offer a rolling three-month period ending today:
 * 
 * ```json
 * {
 *   "caption": "Past 3 months",
 *   "range": "custom",
 *   "range_from": "-3m",
 *   "range_to": 0
 * }
 * 
 * ```
 * 
 */
class DateRange implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait { importUxonObject as private importProperties; }

    private UxonObject $uxon;

    public function __construct(private InputDateRange $widget, UxonObject $uxon)
    {
        $this->importUxonObject($uxon);
    }

    /**
     * Replaces the preset configuration after validating it, including both custom bounds.
     */
    public function importUxonObject(UxonObject $uxon) : void
    {
        $replacement = clone $this;
        $replacement->uxon = new UxonObject();
        $replacement->importProperties($uxon->copy());
        if ($replacement->getRange() === 'custom') {
            foreach (['range_from', 'range_to'] as $name) {
                if ($replacement->uxon->getProperty($name) === null) {
                    throw new WidgetPropertyInvalidValueError($this->widget, "A custom date range requires {$name}.");
                }
            }
        }
        $this->uxon = $replacement->uxon;
    }

    /**
     * {@inheritDoc}
     */
    public function getWidget() : InputDateRange
    {
        return $this->widget;
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }

    /**
     * {@inheritDoc}
     */
    public function exportUxonObject() : UxonObject
    {
        return $this->uxon->copy();
    }

    /**
     * Text displayed for the preset. If omitted, the facade supplies a label.
     * 
     * @uxon-property caption
     * @uxon-type string
     * @uxon-translatable true
     */
    protected function setCaption(mixed $value) : void
    {
        $this->writeTextProperty('caption', $value);
    }

    public function getCaption() : ?string
    {
        return $this->uxon->getProperty('caption');
    }

    /**
     * Additional text shown in the list of presets.
     * 
     * @uxon-property hint
     * @uxon-type string
     * @uxon-translatable true
     */
    protected function setHint(mixed $value) : void
    {
        $this->writeTextProperty('hint', $value);
    }

    public function getHint() : ?string
    {
        return $this->uxon->getProperty('hint');
    }

    /**
     * Optional menu group for this preset. Use single_dates, date_ranges, weeks,
     * months, quarters, or years for a built-in heading, or enter your own heading.
     * Presets with the same group appear together. This does not change the selected dates.
     * 
     * @uxon-property group
     * @uxon-type string
     * @uxon-translatable true
     */
    protected function setGroup(mixed $value) : void
    {
        $this->writeTextProperty('group', $value, false);
    }

    public function getGroup() : ?string
    {
        return $this->uxon->getProperty('group');
    }

    /**
     * Relative calendar period or a custom pair of bounds. Current periods end today.
     * 
     * @uxon-property range
     * @uxon-type [custom,week_current,week_previous,week_next,month_current,month_previous,month_next,quarter_current,quarter_previous,quarter_next,year_current,year_previous,year_next]
     * @uxon-default custom
     */
    protected function setRange(mixed $value) : void
    {
        if ($value !== null && (! is_string($value) || ! preg_match('/^(custom|(?:week|month|quarter|year)_(?:current|previous|next))$/D', $value))) {
            throw new WidgetPropertyInvalidValueError($this->widget, 'Invalid date range type.');
        }
        $this->uxon->setProperty('range', $value);
    }

    public function getRange() : string
    {
        return $this->uxon->getProperty('range') ?? 'custom';
    }

    /**
     * First date of a custom preset, e.g. "-3m".
     * Relative bounds are kept as entered and resolved by the picker on selection.
     * Required when `range` is `custom`.
     * 
     * @uxon-property range_from
     * @uxon-type string|integer
     */
    protected function setRangeFrom(mixed $value) : void
    {
        $this->writeBound('range_from', $value);
    }

    public function getRangeFrom() : ?string
    {
        return $this->uxon->getProperty('range_from');
    }

    /**
     * Last date of a custom preset, e.g. 0 for today.
     * Required when `range` is `custom`.
     * 
     * @uxon-property range_to
     * @uxon-type string|integer
     */
    protected function setRangeTo(mixed $value) : void
    {
        $this->writeBound('range_to', $value);
    }

    public function getRangeTo() : ?string
    {
        return $this->uxon->getProperty('range_to');
    }

    private function writeTextProperty(string $name, mixed $value, bool $allowEmpty = true) : void
    {
        if ($value !== null && (! is_string($value) || (! $allowEmpty && trim($value) === ''))) {
            throw new WidgetPropertyInvalidValueError($this->widget, "Date range preset property {$name} must be " . ($allowEmpty ? 'a string.' : 'a non-empty string.'));
        }
        $this->uxon->setProperty($name, $value);
    }

    private function writeBound(string $name, mixed $value) : void
    {
        if ($value !== null && ! is_int($value) && (! is_string($value) || trim($value) === '')) {
            throw new WidgetPropertyInvalidValueError($this->widget, "Date range preset property {$name} must be an integer or a non-empty string.");
        }
        $this->uxon->setProperty($name, $value);
    }
}
