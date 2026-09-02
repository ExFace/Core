<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * Default implementation of the `iSpecifyInputRows` interface for trigger widgets.
 * 
 * @author Andrej Kabachnik
 */
trait iSpecifyInputRowsTrait
{
    private $inputRows = null;
    
    /**
     * 
     * @return string|NULL
     */
    public function getInputRows() : ?string
    {
        return $this->inputRows;
    }
    
    /**
     * Specify, what rows of the input widget to pass the action of this button: all, selected or only those changed.
     * 
     * By default this is determined automatically based on the action to be performed. However, on rare
     * occasions the option needs to be overridden manually: e.g. if a CallWebService action is actually
     * modifying data, it may need all the rows instead of the selected ones.
     * 
     * Use `changed` to only pass rows, that were actually modified by the user. This is particularly
     * useful for editable spreadsheets, that would otherwise save all their rows regardless of whether
     * they changed or not.
     * 
     * @uxon-property input_rows
     * @uxon-type [auto,all,all_as_subsheet,selected,changed,none]
     * @uxon-default auto
     *
     * @param string $value
     * @return $this
     */
    public function setInputRows(string $value)
    {
        if (! defined('self::INPUT_ROWS_' . strtoupper($value))) {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid value "' . $value . '" for `input_rows` of widget "' . $this->getWidgetType() . '"!');
        }
        $this->inputRows = strtolower($value);
        return $this;
    }
}
