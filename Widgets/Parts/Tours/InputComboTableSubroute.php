<?php
namespace exface\Core\Widgets\Parts\Tours;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Tours\TourSubrouteInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\InputComboTable;

/**
 * This subtour describes, how to use an InputComboTable widget including its lookup dialog.
 * 
 * The subtour will show how to use the lookup dialog for a specific InputComboTable widget. The entire subtour
 * can be completely auto-generated. It will be different for single and multi-select widgets.
 * 
 * You can overwrite all the default substep texts by providing some of the following properties. Adding a
 * property with an empty string will hide the corresponding substep in the tour. Not adding a property will
 * still show the substep, but it will have a default text.
 * 
 * ## Examples
 * 
 * ```
 * {
 *  "title": "",
 *  "body": "You can filter apps here",
 *  "subtour": {
 *      "type": "InputComboTableLookupTour",
 *      "lookup_button": "You can view the complete list here",
 *      "lookup_header": "Use these filters to search for apps",
 *      "lookup_search_button": "Simply click Search",
 *      "lookup_table": "Select the apps you want from the list",
 *      "lookup_selection": "You can view the selected apps here and remove any you no longer want",
 *      "lookup_apply_button": "Finally, click here to apply the selected values",
 *  }
 * }
 * 
 * ```
 */
class InputComboTableSubroute extends AbstractSubroute
{
    private ?string $lookupButton = null;
    private ?string $lookupHeader = null;
    private ?string $lookupSearchButton = null;
    private ?string $lookupTable = null;
    private ?string $lookupSelection = null;
    private ?string $lookupApplyButton = null;
    
    /**
     * Creates a lookup subroute for an InputComboTable using the supplied step texts.
     *
     * @param WidgetInterface $widget
     * @param UxonObject $uxon
     */
    public function __construct(WidgetInterface $widget, UxonObject $uxon)
    {
        if ($widget instanceof InputComboTable) {
            $this->widget = $widget;
            $this->importUxonObject($uxon);
        } else {
            throw new WidgetConfigurationError($widget, 'InputComboTableSubroute can only be used with InputComboTable widgets.');
        }
    }

    /**
     * Returns the custom text for the lookup trigger.
     *
     * @return string|null
     */
    public function getLookupButton(): ?string
    {
        return $this->lookupButton;
    }

    /**
     * Explains how to open the advanced search dialog from the input.
     *
     * This step highlights the trigger for the InputComboTable's `lookup_action`, usually a search
     * button next to the input. Its appearance depends on the facade.
     * Omit this property to use the default text, or set it to an empty string to hide this step.
     *
     * @uxon-property lookup_button
     * @uxon-type string
     * @uxon-translatable true
     *
     * @param string $text
     * @return InputComboTableSubroute
     */
    protected function setLookupButton(string $text): InputComboTableSubroute
    {
        $this->lookupButton = $text;
        return $this;
    }

    /**
     * Returns the custom text for the lookup filter area.
     *
     * @return string|null
     */
    public function getLookupHeader(): ?string
    {
        return $this->lookupHeader;
    }

    /**
     * Explains the filters in the header of the advanced search dialog.
     *
     * Describe which filters users can fill in to narrow the results. The default lookup generates
     * filters from visible columns; customize them through the InputComboTable's `lookup_action.widget.filters`.
     * The lookup action's `hide_header` setting controls whether this area is initially collapsed.
     * Omit this property to use the default text, or set it to an empty string to hide this step.
     *
     * @uxon-property lookup_header
     * @uxon-type string
     * @uxon-translatable true
     *
     * @param string $text
     * @return InputComboTableSubroute
     */
    protected function setLookupHeader(string $text): InputComboTableSubroute
    {
        $this->lookupHeader = $text;
        return $this;
    }

    /**
     * Returns the custom text for the lookup search button.
     *
     * @return string|null
     */
    public function getLookupSearchButton(): ?string
    {
        return $this->lookupSearchButton;
    }

    /**
     * Explains how to run a search after entering filter values in the lookup dialog.
     *
     * This step highlights the dialog's Search button, not the input's trigger for opening the dialog.
     * Use it to tell users to apply their filters and refresh the results.
     * Omit this property to use the default text, or set it to an empty string to hide this step.
     *
     * @uxon-property lookup_search_button
     * @uxon-type string
     * @uxon-translatable true
     *
     * @param string $text
     * @return InputComboTableSubroute
     */
    protected function setLookupSearchButton(string $text): InputComboTableSubroute
    {
        $this->lookupSearchButton = $text;
        return $this;
    }

    /**
     * Returns the custom text for the lookup results table.
     *
     * @return string|null
     */
    public function getLookupTable(): ?string
    {
        return $this->lookupTable;
    }

    /**
     * Explains how to choose entries in the lookup dialog's results table.
     *
     * Describe the relevant columns and whether users should select one or several entries.
     * The lookup normally inherits `multi_select` from the InputComboTable; `lookup_action.multi_select`
     * can override it. Customize the lookup results through `lookup_action.widget`.
     * Omit this property to use the default text, or set it to an empty string to hide this step.
     *
     * @uxon-property lookup_table
     * @uxon-type string
     * @uxon-translatable true
     *
     * @param string $text
     * @return InputComboTableSubroute
     */
    protected function setLookupTable(string $text): InputComboTableSubroute
    {
        $this->lookupTable = $text;
        return $this;
    }

    /**
     * Returns the custom text for the lookup selection area.
     *
     * @return string|null
     */
    public function getLookupSelection(): ?string
    {
        return $this->lookupSelection;
    }

    /**
     * Explains how to review and remove entries from the lookup dialog's current selection.
     *
     * This step describes the selection overview used in multi-select lookups, rather than the results
     * table or the values already applied to the input. It is relevant when the dialog allows multiple
     * selections through `multi_select` and the facade provides a selection overview.
     * Omit this property to use the default text, or set it to an empty string to hide this step.
     *
     * @uxon-property lookup_selection
     * @uxon-type string
     * @uxon-translatable true
     *
     * @param string $text
     * @return InputComboTableSubroute
     */
    protected function setLookupSelection(string $text): InputComboTableSubroute
    {
        $this->lookupSelection = $text;
        return $this;
    }

    /**
     * Returns the custom text for the lookup confirmation button.
     *
     * @return string|null
     */
    public function getLookupApplyButton(): ?string
    {
        return $this->lookupApplyButton;
    }

    /**
     * Explains how to confirm the selection and transfer its values back to the InputComboTable.
     *
     * This step highlights the lookup dialog's confirmation button. Its caption and other settings
     * can be customized through `lookup_action.confirmation_button`; this property only changes the tour text.
     * Omit this property to use the default text, or set it to an empty string to hide this step.
     *
     * @uxon-property lookup_apply_button
     * @uxon-type string
     * @uxon-translatable true
     *
     * @param string $text
     * @return InputComboTableSubroute
     */
    protected function setLookupApplyButton(string $text): InputComboTableSubroute
    {
        $this->lookupApplyButton = $text;
        return $this;
    }
    
    public static function getUxonTemplate() : UxonObject
    {
        return UxonObject::fromArray([
            'lookup_button' => '',
            'lookup_header' => '',
            'lookup_search_button' => '',
            'lookup_table' => '',
            'lookup_selection' => '',
            'lookup_apply_button' => '',
        ]);
    }
}