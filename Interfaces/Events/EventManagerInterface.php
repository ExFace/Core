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
     * @param string $event_name            
     * @param callable $listener_callable            
     * @param int $priority            
     * @return EventDispatcher
     */
    public function addListener($event_name, $listener_callable, $priority = null);

    /**
     * Dispatches an event
     *
     * @param string $event_name            
     * @param EventInterface $event            
     * @return \exface\Core\EventDispatcher
     */
    public function dispatch(EventInterface $event);

    /**
     * Detaches the given listener from the specified event name
     *
     * @param string $event_name            
     * @param callable $listener            
     * @return \exface\Core\EventDispatcher
     */
    public function removeListener($event_name, $listener);

    /**
     * Returns an array of listeners registered for the specified event
     *
     * @param string $event_name            
     * @return callable[]
     */
    public function getListeners($event_name);

    /**
     * Returns TRUE if there are listeners registered for the given event name or FALSE otherwise.
     *
     * @param string $event_name            
     */
    public function hasListeners($event_name);
}
?>