<?php
namespace exface\Core\CommonLogic;

use Symfony\Component\EventDispatcher\EventDispatcher;
use exface\Core\Interfaces\Events\EventManagerInterface;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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

    function __construct(WorkbenchInterface $exface)
    {
        $this->exface = $exface;
        $this->dispatcher = new EventDispatcher();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::addListener()
     */
    public function addListener($eventName, $listener_callable, $priority = null) : EventManagerInterface
    {
        $this->dispatcher->addListener($eventName, $listener_callable, $priority);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::dispatch()
     */
    public function dispatch(EventInterface $event) : EventInterface
    {
        return $this->dispatcher->dispatch($event::getEventName(), $event);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::removeListener()
     */
    public function removeListener($eventName, $listener) : EventManagerInterface
    {
        $this->dispatcher->removeListener($eventName, $listener);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::getListeners()
     */
    public function getListeners($eventName) : array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::hasListeners()
     */
    public function hasListeners($eventName) : bool
    {
        return $this->dispatcher->hasListeners($eventName);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }
    
    public function getSymfonyDispatcher() : EventDispatcherInterface
    {
        return $this->dispatcher;
    }
}
?>