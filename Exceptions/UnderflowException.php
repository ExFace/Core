<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown when performing an invalid operation on an empty container, such as removing an element.
 *
 * It represents errors that cannot be detected at compile time. This is the opposite of OverflowException.
 *
 * @see RuntimeException
 *
 * @author Andrej Kabachnik
 *        
 */
class UnderflowException extends \UnderflowException implements ErrorExceptionInterface, \Throwable
{
    
    use ExceptionTrait;
    
    public function getDefaultAlias()
    {
        return '6VCYFND';
    }
}
?>