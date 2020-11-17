<?php
namespace exface\Core\Events\Workbench;

/**
 * Event fired after the workbench has been stopped.
 * 
 * A the time of this event all services and contexts are still running!
 * 
 * @event exface.Core.Workbench.OnBeforeStop
 *
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeStopEvent extends OnStartEvent
{
    public static function getEventName() : string
    {
        return 'exface.Core.Workbench.OnBeforeStop';
    }
}