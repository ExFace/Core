<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\DataSheets\DataSheetDeleteError;

/**
 * Exception thrown when an item marked already excisting/duplicate is tried to be created.
 * 
 * @author Ralf Mulansky
 *
 */
class DataSheetDuplicatesError extends DataSheetDeleteError
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '7A4ZQVV';
    }
}