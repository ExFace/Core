<?php
namespace exface\Core\Events\DataConnection;

/**
 * Event fired before a data connection is opened.
 *
 * @event exface.Core.DataConnection.OnBeforeConnect
 *
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeConnectEvent extends AbstractDataConnectionEvent
{
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.DataConnection.OnBeforeConnect';
    }
}