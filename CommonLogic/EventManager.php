<?php
namespace exface\Core\CommonLogic;

use Jmikola\WildcardEventDispatcher\WildcardEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use exface\Core\Interfaces\Events\EventManagerInterface;
use exface\Core\Interfaces\Events\EventInterface;

/**
 * The event manager takes care of events in ExFace: registering listeners, dispatching events, etc.
 *
 * This implementation uses the WildCardDispatcher by Jeremy Mikola, which in turn is a decorator for the Symfony EventDispatcher
 * component. The WildCardDispatcher adds support for listening to many events with one listener, like #.Action.Performed
 * to listen to any action being performed!
 */
class EventManager implements EventManagerInterface
{

    private $exface = null;

    private $dispatcher = null;

    function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
        $this->dispatcher = new WildcardEventDispatcher(new EventDispatcher());
    }

    /**
     * Registers a listener for the given event name.
     * The listener can be any PHP-callable.
     *
     * @param string $event_name            
     * @param callable $listener_callable            
     * @param int $priority            
     * @return EventDispatcher
     */
    public function addListener($event_name, $listener_callable, $priority = null)
    {
        $this->dispatcher->addListener($event_name, $listener_callable, $priority);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::dispatch()
     */
    public function dispatch(EventInterface $event)
    {
        $this->dispatcher->dispatch($event->getNameWithNamespace(), $event);
        return $this;
    }

    /**
     * Detaches the given listener from the specified event name
     *
     * @param string $event_name            
     * @param callable $listener            
     * @return \exface\Core\EventDispatcher
     */
    public function removeListener($event_name, $listener)
    {
        $this->dispatcher->removeListener($event_name, $listener);
        return $this;
    }

    /**
     * Returns an array of listeners registered for the specified event
     *
     * @param string $event_name            
     * @return callable[]
     */
    public function getListeners($event_name)
    {
        return $this->dispatcher->getListeners($event_name);
    }

    /**
     * Returns TRUE if there are listeners registered for the given event name or FALSE otherwise.
     *
     * @param string $event_name            
     */
    public function hasListeners($event_name)
    {
        return $this->dispatcher->hasListeners($event_name);
    }

    public function getWorkbench()
    {
        return $this->exface;
    }
}
?>