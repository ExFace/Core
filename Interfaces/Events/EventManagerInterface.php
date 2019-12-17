<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * The event manager takes care of events in ExFace: registering listeners, dispatching events, etc.
 *
 * This implementation uses the WildCardDispatcher by Jeremy Mikola, which in turn is a decorator for the Symfony EventDispatcher
 * component. The WildCardDispatcher adds support for listening to many events with one listener, like #.Action.Performed
 * to listen to any action being performed!
 */
interface EventManagerInterface extends WorkbenchDependantInterface
{

    /**
     * Registers a listener for the given event name.
     * The listener can be any PHP-callable.
     *
     * @param string $eventName            
     * @param callable $listener_callable            
     * @param int $priority            
     * @return EventManagerInterface
     */
    public function addListener($eventName, $listener_callable, $priority = null) : EventManagerInterface;

    /**
     * Dispatches an event and returns it (eventually updated during handling).
     * 
     * Depending on the event type handlers have the option to pass information back
     * to the event trigger by storing it in the event - similarly to javascript's
     * preventDefault() method.
     *
     * @param string $eventName            
     * @param EventInterface $event            
     * @return EventManagerInterface
     */
    public function dispatch(EventInterface $event) : EventInterface;

    /**
     * Detaches the given listener from the specified event name
     *
     * @param string $eventName            
     * @param callable $listener            
     * @return EventManagerInterface
     */
    public function removeListener($eventName, $listener) : EventManagerInterface;

    /**
     * Returns an array of listeners registered for the specified event
     *
     * @param string $eventName            
     * @return callable[]
     */
    public function getListeners($eventName) : array;

    /**
     * Returns TRUE if there are listeners registered for the given event name or FALSE otherwise.
     *
     * @param string $eventName            
     */
    public function hasListeners($eventName) : bool;
}
?>