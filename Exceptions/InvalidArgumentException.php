<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown if an argument is not of the expected type.
 * It represents errors, that should be detected at compile time.
 *
 * For example, if your function requires a number but is instead given a string, throw a InvalidArgumentException
 * stating that the function requires a number.
 *
 * @see LogicException
 *
 * @author Andrej Kabachnik
 *        
 */
class InvalidArgumentException extends \InvalidArgumentException implements ErrorExceptionInterface, \Throwable
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