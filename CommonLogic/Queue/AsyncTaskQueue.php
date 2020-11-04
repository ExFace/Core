<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;

/**
 * Adds the task to the built-in queue for later asynchronous handling.
 * 
 * @author Andrej Kabachnik
 *
 */
class AsyncTaskQueue extends AbstractTaskQueue
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TaskQueueInterface::handle()
     */
    public function handle(TaskInterface $task, array $topics = [], string $producer = null, string $messageId = null, string $userAgent = null): ResultInterface
    {
        $dataSheet = $this->createQueueDataSheet($task, $topics, $producer, $messageId, $userAgent);
        $dataSheet->dataCreate();
        return ResultFactory::createDataResult($task, $dataSheet, 'Added to queue!');
    }
}