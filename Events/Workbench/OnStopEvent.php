<?php
namespace exface\Core\Events\Workbench;

/**
 * Event fired after the workbench has been stopped.
 * 
 * @event exface.Core.Workbench.OnStop
 *
 * @author Andrej Kabachnik
 *        
 */
class OnStopEvent extends OnStartEvent
{
    public static function getEventName() : string
    {
        return 'exface.Core.Workbench.OnStop';
    }
}