<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\TaskQueueInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
/**
 * Interface for all events that trigger a taks save in a queue to run. 
 * 
 * @author rml
 *
 */

interface QueueRunEventInterface extends EventInterface
{
    /**
     * 
     * @param TaskQueueInterface $queue
     * @param TaskInterface $task
     * @param string $queueItemUid
     */
    public function __construct(TaskQueueInterface $queue, TaskInterface $task, string $queueItemUid);    
    
    /**
     * Returns queue that contains the task to run.
     * 
     * @return TaskQueueInterface
     */
    public function getQueue() : TaskQueueInterface;
    
    /**
     * Set the result of the run task.
     * 
     * @param ResultInterface $result
     */
    public function setResult(ResultInterface $result);
    
    /**
     * Check if the taks was already run and returned a result.
     * 
     * @return bool
     */
    public function hasResult() : bool;
    
    /**
     * Get the result of the run task.
     * 
     * @return ResultInterface
     */
    public function getResult() : ResultInterface;
    
    /**
     * Get the uid of the entry in the queue containign the tuask to run.
     * 
     * @return string
     */
    public function getQueueItemUid(): string;
}