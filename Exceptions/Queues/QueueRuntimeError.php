<?php
namespace exface\Core\Exceptions\Queues;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\TaskQueueExceptionInterface;
use exface\Core\Interfaces\TaskQueueInterface;

/**
 * Exception thrown on runtime errors in task queues.
 * 
 * @see RuntimeException
 * 
 * @author Andrej Kabachnik
 *
 */
class QueueRuntimeError extends RuntimeException implements TaskQueueExceptionInterface
{
    private $queue = null;
    
    /**
     * 
     * @param TaskQueueInterface $queue
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
     */
    public function __construct(TaskQueueInterface $queue, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->queue = $queue;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\TaskQueueExceptionInterface::getQueue()
     */
    public function getQueue()
    {
        return $this->queue;
    }
}