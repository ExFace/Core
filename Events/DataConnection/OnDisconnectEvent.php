<?php
namespace exface\Core\Events\DataConnection;

/**
 * Event fired after a data connection has been closed.
 *
 * @event exface.Core.DataConnection.OnDisconnect
 *
 * @author Andrej Kabachnik
 *        
 */
class OnDisconnectEvent extends AbstractDataConnectionEvent
{
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.DataConnection.OnDisconnect';
    }
}