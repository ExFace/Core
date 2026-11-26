<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\Exceptions\NotFoundExceptionInterface;

/**
 * Exception thrown the UID column of a data sheet cannot be found while being required for the current operation.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataSheetUidColumnNotFoundError extends DataSheetRuntimeError implements NotFoundExceptionInterface
{

    public function getDefaultAlias()
    {
        return '6T5V2Q8';
    }
}
?>