<?php
namespace exface\Core\Exceptions\DataSheets;

/**
 * Exception thrown if IO operations in a data sheet fail (i.e.
 * the DataSheet::data_xxx() methods).
 *
 * This is the base class for more specific errors:
 *
 * @see DataSheetWriteError
 * @see DataSheetReadError
 *
 * @author Andrej Kabachnik
 *        
 */
class DataSheetIOError extends DataSheetRuntimeError
{
}
?>