<?php
namespace exface\Core\Interfaces\Widgets;

/**
 * Trigger widgets implementing this interface can control which rows of the input widget are passed to their action.
 * 
 * By default the rows to pass are determined automatically based on the action being performed (e.g. selected
 * rows for most actions, all rows for editable data widgets, etc.). Widgets implementing this interface allow
 * overriding this behavior via the UXON property `input_rows` - e.g. to pass all rows, only the changed ones or
 * none at all.
 * 
 * @author Andrej Kabachnik
 */
interface iSpecifyInputRows extends iTriggerAction
{
    const INPUT_ROWS_ALL = 'all';
    const INPUT_ROWS_ALL_AS_SUBSHEET = 'all_as_subsheet';
    const INPUT_ROWS_SELECTED = 'selected';
    const INPUT_ROWS_CHANGED = 'changed';
    const INPUT_ROWS_AUTO = 'auto';
    const INPUT_ROWS_NONE = 'none';
    
    /**
     * Returns the configured input-rows mode (one of the `INPUT_ROWS_xxx` constants) or NULL for the default.
     * 
     * @return string|NULL
     */
    public function getInputRows() : ?string;
}
