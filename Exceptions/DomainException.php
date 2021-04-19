<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown if a callback refers to an undefined method or if some arguments are missing.
 * It's a logic exception.
 *
 * Basically, this is what you would throw if your code messes up and for example a sanity-check finds a value
 * is �outside the domain�. For example, if you have a method which performs weekday calculations, and for some
 * reason a result of a calculation is outside the 1-7 range (for days in a week), you could throw a DomainException.
 * This is because the value is outside the �domain� for day numbers in a week.
 *
 * @see LogicException
 *
 * @author Andrej Kabachnik
 *        
 */
class DomainException extends \DomainException implements ErrorExceptionInterface, \Throwable
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