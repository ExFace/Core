<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown if a value does not match with a set of values.
 * These errors cannot be detected at compile time.
 *
 * Typically this happens when a function calls another function and expects the return value to
 * be of a certain type or value not including arithmetic or buffer related errors. For example,
 * if you have a list of const�s, and a value must be one of them.
 *
 * @see RuntimeException
 *
 * @author Andrej Kabachnik
 *        
 */
class UnexpectedValueException extends \UnexpectedValueException implements ErrorExceptionInterface, \Throwable
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