<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\DataSheets\DataSheetDeleteError;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown when an item marked as undeletable via the UndeletableBehaviour is tried to be deleted.
 * 
 * @author tmc
 *
 */
class DataSheetDeleteForbiddenError extends DataSheetDeleteError
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '7AYEKR3';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return LoggerInterface::ERROR;
    }
}
