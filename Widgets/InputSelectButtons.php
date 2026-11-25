<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Parts\ConditionalProperty;

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
 * ## Hiding the buttons
 * 
 * The widget's own `hidden_if` controls whether the buttons are shown at all. In a table, it is evaluated
 * for every row with the same rules and limitations as described below for `disabled_if`.
 * 
 * ## Disabling options
 * 
 * A `disabled_if` defined on the `button` of an option does not disable the hidden button, but the
 * option itself: it cannot be selected while the condition is true. If a disabled option is currently
 * selected, it remains selected and is shown as selected AND disabled. In a table, the condition is
 * evaluated for every row - references to columns of the same table use the values of that row.
 * 
 * **Limitations in tables:** only references to columns of the same table are evaluated per row (and
 * re-evaluated when these values change). References to widgets outside the table are evaluated when the
 * row is (re-)rendered, but changing such a widget does not update the options automatically. Row numbers
 * and aggregators (e.g. `=tabelle!COL:SUM`) in references are not evaluated per row either.
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
     * @var UxonObject[]
     */
    private array $optionDisabledIfUxons = [];

    /**
     * @var ConditionalProperty[]|null
     */
    private ?array $optionDisabledIf = null;

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
     * ```
     * 
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
     * A `disabled_if` inside a `button` disables the option, not the (hidden) button. In tables, only references
     * to columns of the same table are evaluated per row - changes of widgets outside the table do not update
     * the options automatically.
     * 
     * IMPORTANT: This feature is experimental. Quality of life features, like auto-complete, may not work as expected when using on-option-selected buttons.
     * 
     * @uxon-property on_option_selected
     * @uxon-type object
     * @uxon-template {"// Enter your option key here":{"lock":"never","button":{"widget_type":"DataButton","action": {"alias": ""}}}}
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
        $this->optionDisabledIfUxons = [];
        $this->optionDisabledIf = null;
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
            $buttonUxon = $optionUxon->getProperty('button');
            // The button is hidden, so its disabled_if is repurposed to control the option itself
            if ($buttonUxon instanceof UxonObject && $buttonUxon->hasProperty('disabled_if')) {
                $this->optionDisabledIfUxons[$optionKey] = $buttonUxon->getProperty('disabled_if');
                $buttonUxon->unsetProperty('disabled_if');
            }
            $this->onOptionSelectedUxons[$optionKey] = $buttonUxon;
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
     * Returns the `disabled_if` conditions of the `on_option_selected` buttons keyed by option.
     * 
     * These conditions do not disable the (always hidden) buttons, but the options themselves: a disabled
     * option cannot be selected by the user. If it is currently selected, it stays selected, but is
     * shown as disabled.
     * 
     * @return ConditionalProperty[]
     */
    public function getOptionsDisabledIf(): array
    {
        if ($this->optionDisabledIf === null) {
            $this->optionDisabledIf = [];
            foreach ($this->optionDisabledIfUxons as $optionKey => $uxon) {
                $this->optionDisabledIf[$optionKey] = new ConditionalProperty($this, 'disabled_if', $uxon);
            }
        }
        return $this->optionDisabledIf;
    }

    /**
     * Returns the `disabled_if` condition of the given option or NULL if there is none.
     * 
     * @param string $optionKey
     * @return ConditionalProperty|null
     */
    public function getOptionDisabledIf(string $optionKey): ?ConditionalProperty
    {
        return $this->getOptionsDisabledIf()[$optionKey] ?? null;
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