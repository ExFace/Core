<?php
namespace exface\Core\Events\DataConnection;

/**
 * Event fired after a query has been performed on a data connection.
 *
 * @event exface.Core.DataConnection.OnQuery
 *
 * @author Andrej Kabachnik
 *        
 */
class OnQueryEvent extends OnBeforeQueryEvent
{
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.DataConnection.OnQuery';
    }
}