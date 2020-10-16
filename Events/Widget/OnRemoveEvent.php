<?php
namespace exface\Core\Events\Widget;

/**
 * Event fired after a widget was removed from a page.
 * 
 * @event exface.Core.Widget.OnRemove
 *
 * @author Andrej Kabachnik
 *        
 */
class OnRemoveEvent extends AbstractWidgetEvent
{
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Widget.OnRemove';
    }
}