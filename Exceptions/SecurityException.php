<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Base class for all kinds of security and access violation exceptions.
 *
 * @author Andrej Kabachnik
 *        
 */
class SecurityException extends RuntimeException
{
    public function getDefaultLogLevel()
    {
        return LoggerInterface::ERROR;
    }
}
?>