<?php
namespace exface\Core\Events\Queue;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\TaskQueueInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Events\QueueRunEventInterface;
use exface\Core\Interfaces\Events\TaskEventInterface;

/**
 * Event fired when a task in a queue should be performed.
 * 
 * @event exface.Core.Queue.OnQueueRun
 *
 * @author Andrej Kabachnik
 *
 */
class OnQueueRunEvent extends AbstractEvent implements QueueRunEventInterface, TaskEventInterface
{
    
    private $queue = null;
    
    private $task = null;
    
    private $result = null;
    
    private $queueItemUid = null;
    
    /**
     * 
     * @param MetaObjectInterface $object
     */
    public function __construct(TaskQueueInterface $queue, TaskInterface $task, string $queueItemUid)
    {
        $this->queue = $queue;
        $this->queueItemUid = $queueItemUid;
        $this->task = $task;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->queue->getWorkbench();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\ErrorEventInterface::getException()
     */
    public function getQueue() : TaskQueueInterface
    {
        return $this->queue;   
    }
    
    public function setResult(ResultInterface $result)
    {
        $this->result = $result;
    }
    
    public function hasResult() : bool
    {
        return $this->result !== null;
    }
    
    public function getResult() : ResultInterface
    {
        return $this->result;
    }
    
    public function getTask(): TaskInterface
    {
        return $this->task;
    }
    
    public function getQueueItemUid(): string
    {
        return $this->queueItemUid;
    }
}