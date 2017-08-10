<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;

/**
 * Exception thrown if trying to set a widget property, that does not exist.
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetPropertyUnknownError extends InvalidArgumentException implements WidgetExceptionInterface
{
    use WidgetExceptionTrait;
    
    public function getDefaultAlias(){
        return '6VYOFZJ';
    }
    
    /**
     *
     * @see WidgetExceptionTrait::mustDestroyWidget()
     */
    protected function mustDestroyWidget()
    {
        return true;
    }
}