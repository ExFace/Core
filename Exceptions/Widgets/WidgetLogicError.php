<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;
use exface\Core\Exceptions\LogicException;

/**
 * Exception thrown if a widget encounters an internal error.
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetLogicError extends LogicException implements WidgetExceptionInterface
{
    
    use WidgetExceptionTrait;

    public static function getDefaultAlias()
    {
        return '6VCYEUC';
    }
}