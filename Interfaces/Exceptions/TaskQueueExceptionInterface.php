<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\TaskQueueInterface;

Interface TaskQueueExceptionInterface
{
    /**
     *
     * @param TaskQueueInterface $queue            
     * @param string $message            
     * @param string $alias            
     * @param \Throwable $previous            
     */
    public function __construct(TaskQueueInterface $queue, $message, $alias = null, $previous = null);

    /**
     *
     * @return TaskQueueInterface
     */
    public function getQueue();
}