<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\WarningExceptionInterface;

/**
 * Exception thrown if a non-severe error occurs.
 * Using warnings enables code to distinguish between a critical error
 * condition, that should prevent further execution an non-critical errors, that do not endager the correct execution.
 *
 * For example, if a template does not support certain widget attributes, the widget can still be drawn - probably not
 * exactly the way, the user intended, but still well useable.
 *
 * @author Andrej Kabachnik
 *        
 */
class Warning extends \Exception implements WarningExceptionInterface, \Throwable
{
    
    use ExceptionTrait;
    
    public function getDefaultAlias()
    {
        return '6VCYFND';
    }
}