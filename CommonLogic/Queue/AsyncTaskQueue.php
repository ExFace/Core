<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Exceptions\Queues\QueueMessageDuplicateError;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Events\Queue\OnQueueRunEvent;
use exface\Core\CommonLogic\UxonObject;

/**
 * Adds the task to the built-in queue for later asynchronous handling.
 * 
 * The queued tasks can now be handled by scheduling the `RunQueuedTasks` action in certain intervals.
 * The action will trigger the `OnQueueRunEvent`, which will be handled by the inherited method
 * `onRunPerformTask()`.
 * 
 * @author Andrej Kabachnik
 *
 */
class AsyncTaskQueue extends AbstractInternalTaskQueue
{    
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $uid
     * @param string $alias
     * @param AppSelectorInterface|string $appSelector
     * @param string $name
     * @param UxonObject $configUxon
     */
    public function __construct(WorkbenchInterface $workbench, string $uid, string $alias, $appSelector = null, string $name = null, UxonObject $configUxon = null)
    {
        parent::__construct($workbench, $uid, $alias, $appSelector, $name, $configUxon);
        $this->getWorkbench()->eventManager()->addListener(OnQueueRunEvent::getEventName(), [$this, 'onRunPerformTask']);
    }
    
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