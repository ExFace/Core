<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;
use exface\Core\Exceptions\OutOfBoundsException;

/**
 * Exception thrown if a required child widget was not found.
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetChildNotFoundError extends OutOfBoundsException implements WidgetExceptionInterface
{
    use WidgetExceptionTrait;
}