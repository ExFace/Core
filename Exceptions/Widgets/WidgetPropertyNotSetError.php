<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;
use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if a required widget property is not set before it is requested by the code.
 *
 * This exception is generally used to indicate that a getter would return null unexpectedly.
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetPropertyNotSetError extends RuntimeException implements WidgetExceptionInterface
{
    use WidgetExceptionTrait;
    
    public function getDefaultAlias(){
        return '6YI5RCU';
    }
}