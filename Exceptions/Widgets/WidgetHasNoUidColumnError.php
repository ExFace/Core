<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Exception thrown if a data widget has no UID column, but it is required for the current configuration (e.g.
 * for button actions).
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetHasNoUidColumnError extends UnexpectedValueException implements WidgetExceptionInterface
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

    public function getDefaultAlias()
    {
        return '6UX6KAQ';
    }
}