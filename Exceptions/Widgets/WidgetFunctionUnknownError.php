<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;

/**
 * Exception thrown if trying to call a widget function, that does not exist.
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetFunctionUnknownError extends InvalidArgumentException implements WidgetExceptionInterface
{
    use WidgetExceptionTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\InvalidArgumentException::getDefaultAlias()
     */
    public function getDefaultAlias(){
        return '7LCYZD0';
    }
}