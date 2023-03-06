<?php
namespace exface\Core\Events\Behavior;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\BehaviorEventInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;

/**
 * Event fired when once a behavior applied its logic
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBehaviorAppliedEvent extends AbstractEvent implements BehaviorEventInterface, MetaObjectEventInterface
{
    private $behavior = null;
    
    private $processedEvent = null;
    
    /**
     * 
     * @param BehaviorInterface $behavior
     * @param EventInterface $processedEvent
     */
    public function __construct(BehaviorInterface $behavior, EventInterface $processedEvent = null)
    {
        $this->behavior = $behavior;
        $this->processedEvent = $processedEvent;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\BehaviorEventInterface::getBehavior()
     */
    public function getBehavior() : BehaviorInterface
    {
        return $this->behavior;
    }
    
    /**
     * 
     * @return EventInterface
     */
    public function getEventProcessed() : EventInterface
    {
        return $this->processedEvent;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->behavior->getWorkbench();
    }
    
    /**
     * 
     * @return MetaObjectInterface
     */
    public function getObject(): MetaObjectInterface
    {
        return $this->behavior->getObject();
    }
}