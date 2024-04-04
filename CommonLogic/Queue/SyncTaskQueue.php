<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Events\Queue\OnQueueRunEvent;
use exface\Core\Exceptions\Queues\QueueRuntimeError;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * Performs the task immediately after inserting in the queue in the same transaction.
 * 
 * @author Andrej Kabachnik
 *
 */
class SyncTaskQueue extends AbstractInternalTaskQueue
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
        return $event->getResult();
    }
}