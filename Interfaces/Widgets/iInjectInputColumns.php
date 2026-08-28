<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Widgets\iTriggerAction;

/**
 * Trigger widgets implementing this interface can declare input-data columns that are added at runtime.
 * 
 * Some actions add columns to their input data on the client side - typically in the facade's JavaScript.
 * Examples are the `dump_setup()` function of a `DataTable` or a map drop-zone adding its
 * `include_target_columns` to the dropped data. Since no widget declares these columns, the server-side
 * action input validation (see `ActionInputValidator`) would flag them as a possible request forgery.
 * 
 * To opt out of that false positive, declare the injected columns as trusted on the triggering button
 * via the UXON property `input_columns_trusted`. The declaration lives on the server-side widget model,
 * so it cannot be forged from the client.
 * 
 * @author Andrej Kabachnik
 */
interface iInjectInputColumns extends iTriggerAction
{
    /**
     * Returns the names of the input-data columns that this trigger declares as trusted (added at runtime).
     * 
     * @return string[]
     */
    public function getInputColumnsTrusted() : array;
}
