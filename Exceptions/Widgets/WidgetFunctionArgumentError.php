<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;

/**
 * Exception thrown if calling a widget functino with wrong or missing arguments.
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetFunctionArgumentError extends InvalidArgumentException implements WidgetExceptionInterface
{
    use WidgetExceptionTrait;
}