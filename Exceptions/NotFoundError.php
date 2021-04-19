<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Base class for all kinds of not-found-errors:
 *
 * @see FileNotFoundError
 * @see DirectoryNotFoundError
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class NotFoundError extends OutOfBoundsException
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\OutOfBoundsException::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return LoggerInterface::ERROR;
    }
}