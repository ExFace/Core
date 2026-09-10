<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * Allows the user to select from a list of values by toggling one or more buttons.
 * 
 * Should a facade not be able to render such buttons, it should gracefully fall back to
 * a regular select menu, radio buttons or a similar visualization.
 * 
 * ## Reacting to selection with buttons
 * 
 * Each selectable option (including the generic empty/NULL options) can be wired up to a
 * hidden `Button` via `on_option_selected`. Whenever the corresponding option is toggled,
 * the facade is expected to trigger that button - this allows attaching arbitrary action
 * logic to these otherwise very basic controls. The button derives its input data from the
 * surrounding container (e.g. a dialog), so `input_mapper`s can be used to transform it.
 * 
 * The keys of `on_option_selected` must match the keys of `selectable_options` (or the
 * generic option keys `` for "none" and `NULL` for "empty"). Every button is forced to be
 * hidden.
 * 
 * Each entry can also define a `lock` behavior controlling how often the button may be
 * triggered:
 * 
 * - `never` (default) - the button is triggered every time the option is pressed.
 * - `until_selection_change` - the button is triggered once and locked until a different
 * option is selected.
 * 
 * ```
 *  {
 *      "widget_type": "InputSelectButtons",
 *      "attribute_alias": "STATUS",
 *      "selectable_options": {
 *          "J": "Yes",
 *          "N": "No"
 *      },
 *      "on_option_selected": {
 *          "NULL": {"button": {"action": {"alias": "..."}}},
 *          "J": {"lock": "until_selection_change", "button": {"action": {"alias": "..."}}}
 *      }
 *  }
 *  
 * ```
 *
 * @author Andrej Kabachnik
 */
class InputSelectButtons extends InputSelect
{
    const LOCK_NEVER = 'never';

    const LOCK_UNTIL_SELECTION_CHANGE = 'until_selection_change';

    /**
     * @var UxonObject[]
     */
    private array $onOptionSelectedUxons = [];

    /**
     * @var string[]
     */
    private array $onOptionSelectedLocks = [];

    /**
     * @var Button[]|null
     */
    private ?array $onOptionSelectedButtons = null;

    /**
     * Assigns a hidden button to be triggered when the given option is selected.
     * 
     * The keys must match the keys of `selectable_options`. Each value is an object with a
     * `button` (a regular `Button` UXON) and an optional `lock` behavior. All buttons are
     * rendered hidden and are only triggered programmatically once the corresponding option
     * is selected. To create a button for the "empty" option use the key `NULL`.
     * 
     * Each entry can also define a `lock` behavior controlling how often the button may be
     * triggered:
     * 
     * - `never` (default) - the button is triggered every time the option is pressed.
     * - `until_selection_change` - the button is triggered once and locked until a different
     * option is selected.
     * 
     * IMPORTANT: This feature is experimental. Quality of life features, like auto-complete, may not work as expected when using on-option-selected buttons.
     * 
     * @uxon-property on_option_selected
     * @uxon-type object
     * @uxon-template {"OPTION": {"lock": "never", "button": {"widget_type":"DataButton",""action": {"alias": ""}}}}
     *
     * @param UxonObject $uxon
     * @throws WidgetConfigurationError
     * @return InputSelectButtons
     */
    public function setOnOptionSelected(UxonObject $uxon): InputSelectButtons
    {
        $this->onOptionSelectedButtons = null;
        $this->onOptionSelectedUxons = [];
        $this->onOptionSelectedLocks = [];
        foreach ($uxon->getPropertiesAll() as $optionKey => $optionUxon) {
            $optionKey = (string) $optionKey;
            if(strtoupper($optionKey) === EXF_LOGICAL_NULL) {
                $optionKey = '';
            }
            if (! ($optionUxon instanceof UxonObject) || $optionUxon->isArray()) {
                throw new WidgetConfigurationError($this, 'Invalid value for "on_option_selected" option "' . $optionKey . '" in widget "' . $this->getWidgetType() . '": expecting an object with a "button" property!');
            }
            if (! $optionUxon->hasProperty('button')) {
                throw new WidgetConfigurationError($this, 'Missing "button" for "on_option_selected" option "' . $optionKey . '" in widget "' . $this->getWidgetType() . '"!');
            }
            $lock = mb_strtolower($optionUxon->getProperty('lock') ?? self::LOCK_NEVER);
            if (! in_array($lock, [self::LOCK_NEVER, self::LOCK_UNTIL_SELECTION_CHANGE], true)) {
                throw new WidgetConfigurationError($this, 'Invalid "lock" value "' . $lock . '" for "on_option_selected" option "' . $optionKey . '" in widget "' . $this->getWidgetType() . '": expecting "' . self::LOCK_NEVER . '" or "' . self::LOCK_UNTIL_SELECTION_CHANGE . '"!');
            }
            $this->onOptionSelectedUxons[$optionKey] = $optionUxon->getProperty('button');
            $this->onOptionSelectedLocks[$optionKey] = $lock;
        }
        return $this;
    }

    /**
     * Returns an array of hidden buttons assigned to selectable options via `on_option_selected`.
     * 
     * The array is keyed by the option value (matching the keys of `getSelectableOptions()`).
     * 
     * @throws WidgetConfigurationError
     * @return Button[]
     */
    public function getOnOptionSelected(): array
    {
        if ($this->onOptionSelectedButtons === null) {
            $this->onOptionSelectedButtons = [];
            $options = $this->getSelectableOptions();
            foreach ($this->onOptionSelectedUxons as $optionKey => $buttonUxon) {
                if (! array_key_exists($optionKey, $options)) {
                    throw new WidgetConfigurationError($this, 'Invalid option "' . $optionKey . '" in "on_option_selected" of widget "' . $this->getWidgetType() . '": no matching entry in "selectable_options"!');
                }
                $button = WidgetFactory::createFromUxonInParent($this, $buttonUxon, 'Button');
                $button->setHidden(true);
                $this->onOptionSelectedButtons[$optionKey] = $button;
            }
        }
        return $this->onOptionSelectedButtons;
    }

    /**
     * Returns the hidden button assigned to the given option or NULL if there is none.
     * 
     * @param string $optionKey
     * @return Button|null
     */
    public function getOnOptionSelectedButton(string $optionKey): ?Button
    {
        return $this->getOnOptionSelected()[$optionKey] ?? null;
    }

    /**
     * Returns the lock behavior (`never` or `until_selection_change`) for the given option.
     * 
     * @param string $optionKey
     * @return string
     */
    public function getOnOptionSelectedLock(string $optionKey): string
    {
        return $this->onOptionSelectedLocks[$optionKey] ?? self::LOCK_NEVER;
    }

    /**
     * Returns the DataColumn this widget is a cell of - or NULL if it is not inside a data widget row.
     * 
     * @return DataColumn|null
     */
    public function getDataColumn(): ?DataColumn
    {
        $parent = $this->getParentByClass(DataColumn::class);
        return $parent instanceof DataColumn ? $parent : null;
    }

    /**
     * Returns TRUE if this widget is rendered as a cell inside a data widget row (e.g. a DataTable).
     * 
     * In this case buttons wired via `on_option_selected` are expected to operate on the data of
     * their own row instead of the surrounding container (e.g. a dialog).
     * 
     * @return bool
     */
    public function isInTable(): bool
    {
        return $this->getDataColumn() !== null;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren(): \Iterator
    {
        yield from parent::getChildren();
        foreach ($this->getOnOptionSelected() as $button) {
            yield $button;
        }
    }
}