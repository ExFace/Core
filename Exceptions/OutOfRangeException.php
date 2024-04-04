<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown when an illegal index was requested.
 * This represents errors that should be detected at compile time.
 *
 * @see LogicException
 * @see OutOfBoundsException for the respective runtime exception
 *     
 * @author Andrej Kabachnik
 *        
 */
class OutOfRangeException extends \OutOfRangeException implements ErrorExceptionInterface, \Throwable
{
    use ExceptionTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '6VCYFND';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return LoggerInterface::CRITICAL;
    }
}
?>