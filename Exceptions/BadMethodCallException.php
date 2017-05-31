<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown if a callback refers to an undefined method or if some arguments are missing.
 * It's a logic exception.
 *
 * Always throw a BadMethodCallException when you create a __call method! Otherwise your code could
 * be calling functions which indeed don�t exist, and you never know about it (for example if you
 * have typed the name wrong).
 *
 * @see LogicException
 *
 * @author Andrej Kabachnik
 *        
 */
class BadMethodCallException extends \BadMethodCallException implements ErrorExceptionInterface, \Throwable
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