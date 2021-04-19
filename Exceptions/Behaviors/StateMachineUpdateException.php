<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\DataSheets\DataSheetWriteError;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown if during an update a forbidden transition between states
 * is detected, or an attribute, which is disabled in a certain state, has been
 * changed.
 *
 * @author Stefan Leupold
 */
class StateMachineUpdateException extends DataSheetWriteError
{
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
