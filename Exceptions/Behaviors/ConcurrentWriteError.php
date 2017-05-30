<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\DataSheets\DataSheetWriteError;

/**
 * Exception thrown a concurrent write attemt (racing condition) is detected.
 *
 * @author Andrej Kabachnik
 *        
 */
class ConcurrentWriteError extends DataSheetWriteError
{

    public function getDefaultAlias()
    {
        return '6T6HZLF';
    }
}
