<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

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
    
    public function getDefaultAlias()
    {
        return '6VCYFND';
    }
}
?>