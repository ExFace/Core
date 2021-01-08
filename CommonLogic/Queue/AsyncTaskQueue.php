<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Exceptions\Queues\QueueMessageDuplicateError;

/**
 * Adds the task to the built-in queue for later asynchronous handling.
 * 
 * @author Andrej Kabachnik
 *
 */
class AsyncTaskQueue extends AbstractInternalTaskQueue
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TaskQueueInterface::handle()
     */
    public function handle(TaskInterface $task, array $topics = [], string $producer = null, string $messageId = null, string $channel = null): ResultInterface
    {
        $dataSheet = $this->enqueue($task, $topics, $producer, $messageId, $channel);
        
        $uid = $dataSheet->getUidColumn()->getValue(0);
        
        try {
            $this->verify($task, $uid, $messageId, $producer);
        } catch (QueueMessageDuplicateError $e) {
            $dataSheet = $this->markDuplicate($dataSheet);
            return ResultFactory::createMessageResult($task, 'Message id "' . $messageId . '" from producer "' . $producer . '" already enqueued - ignoring!');
        }
        
        return ResultFactory::createDataResult($task, $dataSheet, 'Added to queue!');
    }
}