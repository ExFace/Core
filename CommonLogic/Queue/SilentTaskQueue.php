<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\InternalError;
use exface\Core\Factories\ResultFactory;

/**
 * Performs the task immediately without really queueing it.
 * 
 * @author Andrej Kabachnik
 *
 */
class SilentTaskQueue extends AbstractInternalTaskQueue
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Queue\AsyncTaskQueue::handle()
     */
    public function handle(TaskInterface $task, array $topics = [], string $producer = null, string $messageId = null, string $channel = null) : ResultInterface
    {
        try {
            $result = $this->getWorkbench()->handle($task);
        } catch (\Throwable $e) {
            if (! $e instanceof ExceptionInterface){
                $e = new InternalError($e->getMessage(), null, $e);
            }
            $this->getWorkbench()->getLogger()->logException($e);
            
            $uid = $this->enqueue($task, $topics, $producer, $messageId, $channel)->getUidColumn()->getValue(0);
            $this->saveError($uid, $e);
            
            $result = ResultFactory::createErrorResult($task, $e);
            $result->setDataModified(true);
        }
        
        return $result;
    }
}