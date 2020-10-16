<?php
namespace exface\Core\Events\Widget;

/**
 * Event fired after a widget had been prefilled.
 * 
 * @event exface.Core.Widget.OnPrefill
 *
 * @author Andrej Kabachnik
 *        
 */
class OnPrefillEvent extends OnBeforePrefillEvent
{
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Widget.OnPrefill';
    }
}