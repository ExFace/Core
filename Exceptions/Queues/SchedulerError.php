<?php
namespace exface\Core\Exceptions\Queues;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown when the scheduler encounters an error.
 * 
 * @see RuntimeException
 * 
 * @author Andrej Kabachnik
 *
 */
class SchedulerError extends RuntimeException
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return LoggerInterface::ALERT;
    }
}