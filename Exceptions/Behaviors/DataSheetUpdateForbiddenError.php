<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\DataSheets\DataSheetDeleteError;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown when an item marked as unupdatable via the UnupdatableBehaviour is tried to be modified.
 * 
 * @author tmc
 *
 */
class DataSheetUpdateForbiddenError extends DataSheetDeleteError
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '809F0AU';
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
