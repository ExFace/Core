<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\DataSheets\DataSheetDeleteError;

/**
 * Exception thrown when an item marked already excisting/duplicate is tried to be created.
 * 
 * @author Ralf Mulansky
 *
 */
class DataSheetCreateDuplicatesForbiddenError extends DataSheetDeleteError
{
    public function getDefaultAlias()
    {
        return '7A4ZQVV';
    }
    
}
