<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\QueuedTaskStateDataType;
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\Queues\QueueRuntimeError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\Queues\QueueMessageDuplicateError;
use exface\Core\CommonLogic\Tasks\ScheduledTask;
use exface\Core\Interfaces\TaskQueueInterface;
use exface\Core\DataTypes\LogLevelDataType;

/**
 * Base class for queue prototypes saving queues in the model DB.
 * 
 * This class provides default implementations for a basic queue item workflow:
 * 
 * - `enqueue()`
 * - `reserve()`
 * - `verify()`
 * - `saveResult()`
 * - `saveError()`
 * 
 * It also includes default `cleanUp()` logic to purge messages older than defined
 * in the queue config property `days_to_keep_tasks`.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractInternalTaskQueue extends AbstractTaskQueue
{
    private $daysToKeepTasks = null;
    
    private $errorLogLevel = null;
    
    /**
     * 
     * @param string $queueItemUid
     * @param array $readAttributeAliases
     * @throws RuntimeException
     * @return DataSheetInterface
     */
    protected function reserve(string $queueItemUid, array $readAttributeAliases = []) : DataSheetInterface
    {
        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUED_TASK');
        $dataSheet->getColumns()->addFromSystemAttributes();
        $dataSheet->getColumns()->addFromExpression('STATUS');
        if (! empty($readAttributeAliases)) {
            $dataSheet->getColumns()->addMultiple($readAttributeAliases);
        }
        $dataSheet->getFilters()->addConditionFromString('UID', $queueItemUid);
        $dataSheet->dataRead();
        
        $currentStatus = $dataSheet->getCellValue('STATUS', 0);
        if (! ($currentStatus == QueuedTaskStateDataType::STATUS_RECEIVED || $currentStatus == QueuedTaskStateDataType::STATUS_QUEUED)) {
            $statusType = QueuedTaskStateDataType::fromValue($this->getWorkbench(), $currentStatus);
            throw new RuntimeException('Cannot start queued task "' . $queueItemUid . '": invalid status "' . $statusType->getLabelOfValue() . '"!');
        }
        
        $dataSheet->setCellValue('STATUS', 0, QueuedTaskStateDataType::STATUS_INPROGRESS);
        $dataSheet->setCellValue('QUEUE', 0, $this->getUid());
        
        $dataSheet->dataUpdate();
        
        return $dataSheet;
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param ResultInterface $result
     * @throws QueueRuntimeError
     * @return DataSheetInterface
     */
    protected function saveResult(DataSheetInterface $dataSheet, ResultInterface $result, float $duration = null) : DataSheetInterface
    {
        try {
            if ($dataSheet->getColumns()->hasSystemColumns()) {
                $dataSheet->getColumns()->addFromSystemAttributes();
                $dataSheet->dataRead();
            }
            $dataSheet->setCellValue('RESULT_CODE', 0, $result->getResponseCode());
            $dataSheet->setCellValue('RESULT', 0, $result->getMessage());
            $dataSheet->setCellValue('STATUS', 0, QueuedTaskStateDataType::STATUS_DONE);
            $dataSheet->setCellValue('PROCESSED_ON', 0, DateTimeDataType::now());
            $dataSheet->setCellValue('DURATION_MS', 0, $duration);
            $dataSheet->setCellValue('QUEUE', 0, $this->getUid());
            $dataSheet->dataUpdate(false);
        } catch (\Throwable $e) {
            throw new QueueRuntimeError($this, 'Cannot save task result in queue "' . $this->getName() . '": ' . $e->getMessage(), null, $e);
        }
        
        return $dataSheet;
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param ExceptionInterface $exception
     * @throws QueueRuntimeError
     * @return DataSheetInterface
     */
    protected function saveError(DataSheetInterface $dataSheet, ExceptionInterface $exception, string $status = QueuedTaskStateDataType::STATUS_ERROR, float $duration = null) : DataSheetInterface
    {
        try {
            if ($dataSheet->getColumns()->hasSystemColumns()) {
                $dataSheet->getColumns()->addFromSystemAttributes();
                $dataSheet->dataRead();
            }
            
            if ($exception instanceof QueueRuntimeError && $exception->getPrevious() !== null) {
                $message = $exception->getPrevious()->getMessage();
            } else {
                $message = $exception->getMessage();
            }
            
            $dataSheet->setCellValue('STATUS', 0, $status);
            $dataSheet->setCellValue('ERROR_MESSAGE', 0, $message);
            $dataSheet->setCellValue('ERROR_LOGID', 0, $exception->getId());
            $dataSheet->setCellValue('PROCESSED_ON', 0, DateTimeDataType::now());
            $dataSheet->setCellValue('DURATION_MS', 0, $duration);
            $dataSheet->setCellValue('QUEUE', 0, $this->getUid());
            $dataSheet->dataUpdate(false);
        } catch (\Throwable $e) {
            throw new QueueRuntimeError($this, 'Cannot save task result in queue "' . $this->getName() . '": ' . $e->getMessage(), null, $e);
        }
        
        return $dataSheet;
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param string[] $topics
     * @param string $producer
     * @param string $messageId
     * @param string $channel
     * @return DataSheetInterface
     */
    protected function enqueue(TaskInterface $task, array $topics = [], string $producer, string $messageId = null, string $channel = null) : DataSheetInterface
    {
        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUED_TASK');
        $dataSheet->getColumns()->addFromUidAttribute();
        if ($task->hasParameter('assignedOn')) {
            $assignedOn = $task->getParameter('assignedOn');
        } else {
            $assignedOn = DateTimeDataType::now();
        }
        $dataSheet->getColumns()->addFromSystemAttributes();
        
        $userAgent = null;
        if ($task instanceof HttpTaskInterface) {
            $request = $task->getHttpRequest();
            if ($request->hasHeader('User-Agent')) {
                $userAgent = $request->getHeader('User-Agent')[0];
            }
        }
        
        $dataSheet->addRow([
            'TASK_UXON' => $task->exportUxonObject()->toJson(),
            'STATUS' => QueuedTaskStateDataType::STATUS_QUEUED,
            'OWNER' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
            'PRODUCER' => $producer,
            'MESSAGE_ID' => $messageId,
            'TASK_ASSIGNED_ON' => $assignedOn,
            'TOPICS' => implode(', ', $topics),
            'CHANNEL' => $channel,
            'USER_AGENT' => $userAgent,
            'QUEUE' => $this->getUid()
        ]);
        
        if ($task instanceof ScheduledTask) {
            $dataSheet->setCellValue('SCHEDULER', 0, $task->getSchedulerUid());
        }
        
        $dataSheet->dataCreate();
        
        return $dataSheet;
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param string $queueItemUid
     * @param string $messageId
     * @param string $producer
     * @throws QueueMessageDuplicateError
     */
    protected function verify(TaskInterface $task, string $queueItemUid, string $messageId = null, string $producer = null)
    {
        if ($this->getMessageIdsUniquePerProducer()) {
            if ($messageId === null || $producer === null) {
                throw new QueueMessageDuplicateError($this, 'Cannot check if message id is unique: message and/or producer id empty!');
            }
        }
        
        $duplicates = $this->findDuplicates($queueItemUid, $messageId, $producer);
        if ($duplicates->countRows() > 0) {
            throw new QueueMessageDuplicateError($this, 'Message "' . $messageId . '" from producer "' . $producer . '" already enqueued in "' . $this->getName() . '" on ' . $duplicates->getCellValue('ENQUEUED_ON', 0) . '!');
        }
        
        return;
    }
    
    /**
     * Returns a data sheet with potential duplicates of the given queue item
     * 
     * Another queue item is concidered a duplicate if
     * - It has the same message and producer ids
     * - It has been performed or should be performed
     * 
     * @param string $queueItemUid
     * @param string $messageId
     * @param string $producer
     * @return DataSheetInterface
     */
    protected function findDuplicates(string $queueItemUid, string $messageId, string $producer) : DataSheetInterface
    {
        $sheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUED_TASK');
        $sheet->getColumns()
            ->addFromSystemAttributes()
            ->addMultiple([
                'STATUS',
                'PARENT_ITEM',
                'ENQUEUED_ON'
            ]);
        $sheet->getFilters()
            ->addConditionFromString('MESSAGE_ID', $messageId, ComparatorDataType::EQUALS)
            ->addConditionFromString('PRODUCER', $producer, ComparatorDataType::EQUALS)
            ->addConditionFromString('QUEUE', $this->getUid(), ComparatorDataType::EQUALS)
            ->addConditionFromString('STATUS', QueuedTaskStateDataType::STATUS_CANCELED, ComparatorDataType::EQUALS_NOT)
            ->addConditionFromString('STATUS', QueuedTaskStateDataType::STATUS_REPLACED, ComparatorDataType::EQUALS_NOT)
            ->addConditionFromString('STATUS', QueuedTaskStateDataType::STATUS_DUPLICATE, ComparatorDataType::EQUALS_NOT);
        
        $sheet->getSorters()->addFromString('CREATED_ON', 'DESC');
        
        $sheet->dataRead();
        
        // Get row data for the item being checked
        foreach ($sheet->getRows() as $i => $row) {
            if ($row['UID'] === $queueItemUid) {
                $sheet->removeRow($i);
                break;
            }
        }
        
        return $sheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TaskQueueInterface::cleanUp()
     */
    public function cleanUp() : string
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUED_TASK');
        $ds->getFilters()->addConditionFromString('QUEUE', $this->getUid(), ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('ENQUEUED_ON', (-1)*$this->getDaysToKeepTasks(), ComparatorDataType::LESS_THAN);
        $cnt = $ds->dataDelete();
        return 'Cleaned up queue "' . $this->getName() . '" removing ' . $cnt . ' expired messages.';
    }
    
    /**
     * 
     * @param int $default
     * @return int
     */
    protected function getDaysToKeepTasks(int $default = 30) : int
    {
        return $this->daysToKeepTasks ?? $default;
    }
    
    /**
     * Processed tasks will be removed after this time (in days) when the workbench cleanup is run.
     * 
     * @uxon-property days_to_keep_tasks
     * @uxon-type integer
     * @uxon-default 30
     * 
     * @param int $value
     * @return AbstractInternalTaskQueue
     */
    protected function setDaysToKeepTasks(int $value) : AbstractInternalTaskQueue
    {
        $this->daysToKeepTasks = $value;
        return $this;
    }
    
    /**
     * 
     * @param string $defaultPsr3Level
     * @return string
     */
    public function getErrorLogLevel(string $defaultPsr3Level) : string
    {
        return $this->errorLogLevel ?? $defaultPsr3Level;
    }
    
    /**
     * The PSR-3 log level to use on errors in this queue (overriding the original log level or the error)
     * 
     * @uxon-property error_log_level
     * @uxon-type [debug, info, notice, warning, error, critical, alert, emergency]
     * 
     * @param string $psr3Level
     * @return TaskQueueInterface
     */
    protected function setErrorLogLevel(string $psr3Level) : TaskQueueInterface
    {
        $this->errorLogLevel = LogLevelDataType::cast($psr3Level);
        return $this;
    }
}