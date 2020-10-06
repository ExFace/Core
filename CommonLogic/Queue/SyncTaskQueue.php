<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use Symfony\Component\Console\Exception\ExceptionInterface;
use exface\Core\Exceptions\InternalError;
use exface\Core\DataTypes\QueuedTaskStateDataType;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Queue\OnQueueRunEvent;
use exface\Core\Factories\DataSheetFactory;

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
    public function handle(TaskInterface $task, array $topics = [], string $producer = null, string $messageId = null) : ResultInterface
    {
        $dataSheet = $this->createQueueDataSheet($task, $topics, $producer, $messageId);
        
        $dataSheet->dataCreate();
        
        $event = $this->getWorkbench()->eventManager()->dispatch(new OnQueueRunEvent($this, $task, $dataSheet->getUidColumn()->getValue(0)));
        if ($result = $event->getResult()) {
            return $result;
        } else {
            // TODO
        }
    }
    
    public function onRunPerformTask(OnQueueRunEvent $event)
    {
        if ($event->getQueue() !== $this) {
            return;
        }
        $transaction = $this->getWorkbench()->data()->startTransaction();
        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUED_TASK');
        $dataSheet->getColumns()->addFromSystemAttributes();
        $dataSheet->getColumns()->addFromExpression('STATUS');
        $dataSheet->getFilters()->addConditionFromString('UID', $event->getQueueItemUid());
        $dataSheet->dataRead();
        $dataSheet->setCellValue('STATUS', 0, QueuedTaskStateDataType::STATUS_DONE);
        $dataSheet->dataUpdate(false, $transaction);
        $transaction->commit();
        try {            
            $task = $event->getTask();
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $result = $this->getWorkbench()->handle($task);
            $dataSheet->getColumns()->addFromExpression('RESULT');
            $dataSheet->setCellValue('RESULT', 0, $result->getResponseCode() . ' - ' . $result->getMessage());
            $dataSheet->setCellValue('STATUS', 0, QueuedTaskStateDataType::STATUS_DONE);
            $dataSheet->dataUpdate(false, $transaction);
            $transaction->commit();
            $event->setResult($result);
            return;
        } catch (\Throwable $e) {
            if (! $e instanceof ExceptionInterface){
                $e = new InternalError($e->getMessage(), null, $e);
            }
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $dataSheet->setCellValue('STATUS', 0, QueuedTaskStateDataType::STATUS_ERROR);
            $dataSheet->getColumns()->addFromExpression('ERROR_MESSAGE');
            $dataSheet->getColumns()->addFromExpression('ERROR_LOGID');
            $dataSheet->setCellValue('ERROR_MESSAGE', 0, $e->getMessage());
            $dataSheet->setCellValue('ERROR_LOGID', 0, $e->getAlias());
            $dataSheet->dataUpdate(false, $transaction);
            $transaction->commit();
            throw $e;
        }
        return;
    }
}