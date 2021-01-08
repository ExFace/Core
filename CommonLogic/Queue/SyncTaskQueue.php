<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Exceptions\InternalError;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Queue\OnQueueRunEvent;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\Tasks\ResultError;
use exface\Core\Exceptions\Queues\QueueRuntimeError;
use exface\Core\Exceptions\Queues\QueueMessageDuplicateError;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\DataTypes\QueuedTaskStateDataType;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Performs the task immediately after inserting in the queue in the same transaction.
 * 
 * @author Andrej Kabachnik
 *
 */
class SyncTaskQueue extends AsyncTaskQueue
{
    public function __construct(WorkbenchInterface $workbench, string $uid, string $alias, $appSelector = null, string $name = null, UxonObject $configUxon = null)
    {
        parent::__construct($workbench, $uid, $alias, $appSelector, $name, $configUxon);
        $this->getWorkbench()->eventManager()->addListener(OnQueueRunEvent::getEventName(), [$this, 'onRunPerformTask']);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Queue\AsyncTaskQueue::handle()
     */
    public function handle(TaskInterface $task, array $topics = [], string $producer = null, string $messageId = null, string $channel = null) : ResultInterface
    {
        $dataSheet = $this->enqueue($task, $topics, $producer, $messageId, $channel);
        
        $uid = $dataSheet->getUidColumn()->getValue(0);
        
        $event = $this->getWorkbench()->eventManager()->dispatch(new OnQueueRunEvent($this, $task, $uid));
        if (! $event->hasResult()) {
            throw new QueueRuntimeError($this, "Performing the task with UID '{$uid}' did not produce any result or error");
        }
        $result = $event->getResult();
        if ($result instanceof ResultError) {
            throw $result->getException();
        }
        return $result;
    }
    
    /**
     * 
     * @param OnQueueRunEvent $event
     */
    public function onRunPerformTask(OnQueueRunEvent $event)
    {
        if ($event->getQueue() !== $this) {
            return;
        } 
        
        try {            
            $start = microtime(true);
            $ds = $this->reserve($event->getQueueItemUid(), ['MESSAGE_ID', 'PRODUCER']);
            
            $messageId = $ds->getCellValue('MESSAGE_ID', 0);
            $producer = $ds->getCellValue('PRODUCER', 0);
            
            try {
                $this->verify($event->getTask(), $event->getQueueItemUid(), $messageId, $producer);
            } catch (QueueMessageDuplicateError $e) {
                $this->saveError($ds, $e, QueuedTaskStateDataType::STATUS_DUPLICATE);
                $event->setResult(ResultFactory::createMessageResult($event->getTask(), 'Message id "' . $messageId . '" from producer "' . $producer . '" already enqueued - ignoring!'));
                return;
            }
            
            $task = $event->getTask();
            $result = $this->getWorkbench()->handle($task);
            $this->saveResult($ds, $result, (microtime(true) - $start));
            
            $event->setResult($result);
        } catch (\Throwable $e) {
            if (! $e instanceof ExceptionInterface) {
                $e = new InternalError($e->getMessage(), null, $e);
            }
            
            $this->getWorkbench()->getLogger()->logException($e);
            
            $this->saveError($ds, $e, QueuedTaskStateDataType::STATUS_ERROR, (microtime(true) - $start));
            
            $result = ResultFactory::createErrorResult($task, $e);
            $result->setDataModified(true);
            $event->setResult($result);
        }
        return;
    }
}