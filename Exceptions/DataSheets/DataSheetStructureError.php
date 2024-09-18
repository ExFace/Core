<?php
namespace exface\Core\Exceptions\DataSheets;

/**
 * Exception thrown if unexpected DataSheet structure occurs: e.g. totals or sorters over non-attribute columns, unexpected row count, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataSheetStructureError extends DataSheetRuntimeError
{
}
?>