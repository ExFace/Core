<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown if a non-ExFace exception is caught. 
 * 
 * This is a wrapper for unknown exception types. It enables all exceptions
 * to produce debug widgets.
 *
 * @author Andrej Kabachnik
 *        
 */
class InternalError extends LogicException
{
    public function getDefaultLogLevel(){
        return LoggerInterface::CRITICAL;
    }
}
?>