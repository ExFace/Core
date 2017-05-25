<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown when adding an element to a full container.
 * This represents errors that cannot be detected at compile time.
 *
 * If your class acts like a container, you can use OverflowException when the object is full, but someone is trying to add more
 * items into it.
 *
 * @see RuntimeException
 *
 * @author Andrej Kabachnik
 *        
 */
class OverflowException extends \OverflowException implements ErrorExceptionInterface, \Throwable
{
    
    use ExceptionTrait;
}
?>