<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\Exceptions\NotFoundExceptionInterface;

/**
 * Exception thrown if a requested column cannot be found in the data sheet.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataSheetColumnNotFoundError extends DataSheetRuntimeError implements NotFoundExceptionInterface
{

    public function getDefaultAlias()
    {
        return '6T5V6WE';
    }
}
?>