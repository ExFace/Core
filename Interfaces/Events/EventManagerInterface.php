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

    /**
     * Registers a regular listener for the given event name.
     * 
     * The listener can be any PHP-callable.
     *
     * @param string $eventName            
     * @param callable $listener_callable            
     * @param int $priority            
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