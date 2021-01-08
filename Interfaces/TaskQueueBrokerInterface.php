<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;

/**
 * Interface for task queue brokers / task routers.
 * 
 * @author Andrej Kabachnik
 *
 */
interface TaskQueueBrokerInterface extends TaskHandlerInterface
{
    /**
     * Puts the task in the queue using it's internal handling logic.
     * 
     * @param TaskInterface $task
     * @param string[] $topics
     * @param string $producer
     * @param string $messageId
     * @param string $channel
     * 
     * @return ResultInterface
     * 
     * @see \exface\Core\Interfaces\TaskHandlerInterface::handle()
     */
    public function handle(TaskInterface $task, array $topics = [], string $producer = null, string $messageId = null, string $channel = null) : ResultInterface;
}