<?php
namespace exface\Core\Events\Behavior;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\BehaviorEventInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Event fired when if a behavior is applicable, but before its logic is performed
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeBehaviorAppliedEvent extends AbstractEvent implements BehaviorEventInterface, MetaObjectEventInterface
{
    private $behavior = null;
    
    private $processedEvent = null;
    
    private $logbook = null;
    
    /**
     * 
     * @param BehaviorInterface $behavior
     * @param EventInterface $processedEvent
     */
    public function __construct(BehaviorInterface $behavior, EventInterface $processedEvent = null, LogBookInterface $logbook = null)
    {
        $this->behavior = $behavior;
        $this->processedEvent = $processedEvent;
        $this->logbook = $logbook;
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
     * @return EventInterface|NULL
     */
    public function getEventProcessed() : ?EventInterface
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
    
    /**
     * 
     * @return LogBookInterface|NULL
     */
    public function getLogbook() : ?LogBookInterface
    {
        return $this->logbook;
    }
}