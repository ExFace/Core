<?php

namespace exface\Core\Exceptions\DataSheets;

/**
 * Exception thrown if a value required for some operation on the data sheet is missing (e.g.
 * a required
 * attribute when creating an object instance).
 *
 * @author Andrej Kabachnik
 *        
 */
class DataSheetMissingRequiredValueError extends DataSheetRuntimeError
{
}
?>