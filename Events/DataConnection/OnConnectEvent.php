<?php
namespace exface\Core\Events\DataConnection;

/**
 * Event fired after a data connection has been established.
 *
 * @event exface.Core.DataConnection.OnConnect
 *
 * @author Andrej Kabachnik
 *        
 */
class OnConnectEvent extends AbstractDataConnectionEvent
{
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.DataConnection.OnConnect';
    }
}