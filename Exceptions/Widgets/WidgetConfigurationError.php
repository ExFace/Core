<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;

/**
 * Exception thrown if a widget fails to read it's configuration or an invalid configuration value is passed.
 *
 * This exception will be typically thrown by setters in the widget class. This way, configuration values being
 * set programmatically and via UXON import can be checked in the same manner.
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetConfigurationError extends RuntimeException implements WidgetExceptionInterface
{
    use WidgetExceptionTrait;
    
    /**
     * 
     * @see WidgetExceptionTrait::mustDestroyWidget()
     */
    protected function mustDestroyWidget()
    {
        return true;
    }
}