<?php
namespace exface\Core\Events\DataConnection;

/**
 * Event fired before an data connection is closed.
 *
 * @event exface.Core.DataConnection.OnBeforeDisconnect
 *
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeDisconnectEvent extends AbstractDataConnectionEvent
{
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.DataConnection.OnBeforeDisconnect';
    }
}