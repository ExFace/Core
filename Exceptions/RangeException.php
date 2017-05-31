<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown to indicate range errors during program execution (runtime).
 *
 * This is an exception that should be thrown when a value is out of some specific range. Normally this
 * means there was an arithmetic error other than under/overflow. It�s similar to DomainException in its
 * intended purpose, but it should be used in cases where the error is going to be caused in a runtime scenario.
 *
 * @author Andrej Kabachik
 *        
 */
class RangeException extends \RangeException implements ErrorExceptionInterface, \Throwable
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
        return LoggerInterface::ERROR;
    }
}
?>