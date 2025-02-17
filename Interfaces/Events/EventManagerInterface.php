<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * Controls events within the workbench: registers listeners, dispatches events, etc.
 *
 * There are two types of event listeners:
 * 
 * - regular listeners are registered at runtime (via `addListener()`)
 * - static listeners are registered once (typically by an installer) and triggered
 * automatically every time the event is fired. They are stored in the `System.config.json`.
 * 
 * @author Andrej Kabachnik
 */
interface EventManagerInterface extends WorkbenchDependantInterface
{
    const PRIORITY_MAX = '999999';
    const PRIORITY_MIN = '-999999';

    /**
     * Registers a regular listener for the given event name.
     * 
     * The listener can be any PHP-callable.
     * 
     * Set a higher priority to ensure a listener is called earlier. If no $priority is specified, listeners 
     * are called in the order they were added.
     * 
     * Use EventManagerInterface::PRIORITY_MAX and PRIORITY_MIN to ensure your listener is call first or
     * last. If multiple MAX-priority listeners are added, the last one added will be called first.
     * Similarly the last added MIN-priority listener will be called last. 
     *
     * @param string $eventName            
     * @param callable $listener_callable            
     * @param int|NULL $priority The higher this value, the earlier the listener will be triggered in the chain            
     * @return EventManagerInterface
     */
    public function addListener(string $eventName, callable $listener_callable, int $priority = null) : EventManagerInterface;

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
     * Detaches the given regular listener from the specified event name.
     *
     * @param string $eventName            
     * @param callable $listener            
     * @return EventManagerInterface
     */
    public function removeListener(string $eventName, callable $listener) : EventManagerInterface;

    /**
     * Returns an array of all listeners registered for the specified event (incl. static listeners!)
     *
     * @param string $eventName            
     * @return callable[]
     */
    public function getListeners(string $eventName) : array;

    /**
     * 
     * @param string $eventName
     * @return bool
     */
    public function hasListeners(string $eventName) : bool;
    
    /**
     * Registers a regular listener for the given event name.
     * 
     * The listener MUST be a static PHP-callable: e.g. `MyClass::staticMethod` or
     * `["myClass", "staticMethod"]`.
     * 
     * @param string $eventName
     * @param callable $listener_callable
     * @param int $priority
     * @return EventManagerInterface
     */
    public function addStaticListener(string $eventName, callable $listener_callable, int $priority = null) : EventManagerInterface;
    
    /**
     * Detaches the given static listener from the specified event name.
     * 
     * @param string $eventName
     * @param callable $listener_callable
     * @return EventManagerInterface
     */
    public function removeStaticListener(string $eventName, callable $listener_callable) : EventManagerInterface;
    
    /**
     * 
     * @param string $eventName
     * @return bool
     */
    public function hasStaticListeners(string $eventName) : bool;
    
    /**
     * 
     * @param string $eventName
     * @return array
     */
    public function getStaticListeners(string $eventName) : array;
}
?>