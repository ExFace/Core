<?php
namespace exface\Core\CommonLogic;

use Symfony\Component\EventDispatcher\EventDispatcher;
use exface\Core\Interfaces\Events\EventManagerInterface;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\AppInterface;

/**
 * Default implementation of the EventManagerInterface based on the Symfony EventDispatcher component.
 * 
 * @see EventManagerInterface
 * 
 * @author Andrej Kabachnik
 */
class EventManager implements EventManagerInterface
{

    private $exface = null;

    private $dispatcher = null;

    /**
     * 
     * @param WorkbenchInterface $exface
     */
    function __construct(WorkbenchInterface $exface)
    {
        $this->exface = $exface;
        $this->dispatcher = new EventDispatcher();
        $this->registerStaticListeners();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::addListener()
     */
    public function addListener(string $eventName, callable $listener_callable, int $priority = null) : EventManagerInterface
    {
        $this->dispatcher->addListener($eventName, $listener_callable, $priority ?? 0);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::dispatch()
     */
    public function dispatch(EventInterface $event) : EventInterface
    {
        return $this->dispatcher->dispatch($event, $event::getEventName());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::removeListener()
     */
    public function removeListener(string $eventName, callable $listener) : EventManagerInterface
    {
        $this->dispatcher->removeListener($eventName, $listener);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::getListeners()
     */
    public function getListeners(string $eventName) : array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::hasListeners()
     */
    public function hasListeners(string $eventName) : bool
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::addStaticListener()
     */
    public function addStaticListener(string $eventName, callable $listener_callable, int $priority = null) : EventManagerInterface
    {
        if ((is_array($listener_callable) && ! is_string($listener_callable[0])) || ! is_string($listener_callable)) {
            throw new InvalidArgumentException('Invalid static event callable "' . print_r($listener_callable, true) . '"!');
        }
        
        $config = $this->getWorkbench()->getConfig();
        $listeners = $config->getOption('EVENTS.STATIC_LISTENERS')->toArray();
        $eventListeners = $listeners[$eventName];
        if ($eventListeners) {
            $existingPrio = array_search($listener_callable, $eventListeners, true);
            
            if ($existingPrio === false) {
                $listeners[$eventName] = array_merge($eventListeners, [$listener_callable]);
            } elseif ($existingPrio !== $priority) {
                $this->removeListener($eventName, $listener_callable);
                unset($eventListeners[$existingPrio]);
                if ($priority !== null) {
                    $listeners[$eventName] = array_splice($eventListeners, $priority, 0, $listener_callable);
                } else {
                    $listeners[$eventName] = array_merge($eventListeners, [$listener_callable]);
                }
                $this->addListener($eventName, $listener_callable, $priority);
            }
        }
        
        $config->setOption('EVENTS.STATIC_LISTENERS', new UxonObject($listeners), AppInterface::CONFIG_SCOPE_SYSTEM);
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::removeStaticListener()
     */
    public function removeStaticListener(string $eventName, callable $listener_callable) : EventManagerInterface
    {
        $config = $this->getWorkbench()->getConfig();
        $listeners = $config->getOption('EVENTS.STATIC_LISTENERS')->toArray();
        
        if ($listeners[$eventName] === null || ($prio = array_search($listener_callable, $listeners[$eventName])) === false) {
            return $this;
        }
        
        unset($listeners[$eventName][$prio]);
        
        $config->setOption('EVENTS.STATIC_LISTENERS', new UxonObject($listeners), AppInterface::CONFIG_SCOPE_SYSTEM);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::getStaticListeners()
     */
    public function getStaticListeners(string $eventName) : array
    {
        return $this->getWorkbench()->getConfig()->getOption('EVENTS.STATIC_LISTENERS')->toArray()[$eventName] ?? [];
    }
    
    /**
     * @return void
     */
    protected function registerStaticListeners()
    {
        foreach ($this->getWorkbench()->getConfig()->getOption('EVENTS.STATIC_LISTENERS')->toArray() as $event => $callables) {
            foreach ($callables as $priority => $callable)
            $this->addListener($event, $callable, $priority);
        }
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\EventManagerInterface::hasStaticListeners()
     */
    public function hasStaticListeners(string $eventName) : bool
    {
        return ! empty($this->getStaticListeners($eventName));
    }
}