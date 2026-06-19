<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\CommonLogic\Debugger;
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
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;
use exface\Core\Events\Queue\OnQueueRunEvent;

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
 * - `onRunPerformTask()`
 * 
 * The idea is, that tasks are stored in the model and can be easily processed by
 * letting the workbench handle them. Concrete implementations need to take care
 * of passing the task to the workbench.
 * 
 * In the simplest case, this should be done every time the `OnQueueRunEvent` is
 * fired for this queue. This is what the action `RunQueuedTasks` does. This class
 * even provides a default handler for this event - `onRunPerfomTask()`. The
 * subclasses `AsnycTaskQueue` and `SyncTaskQueue` register the default handler
 * as event listener and `SyncTaskQueue` even fires the event immediately.
 * 
 * This class also includes default `cleanUp()` logic to purge messages older than defined
 * in the queue config property `days_to_keep_tasks`.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractInternalTaskQueue extends AbstractTaskQueue
{
    private $daysToKeepTasks = null;
    
    private $errorLogLevel = null;
    
    private bool $skipTaskIfAlreadyRunning = false;
    private ?int $processId = null;

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
        $dataSheet->getColumns()->addFromExpression('PID');
        if (! empty($readAttributeAliases)) {
            $dataSheet->getColumns()->addMultiple($readAttributeAliases);
        }
        $dataSheet->getFilters()->addConditionFromString('UID', $queueItemUid, ComparatorDataType::EQUALS);
        $dataSheet->dataRead();
        
        $currentStatus = $dataSheet->getCellValue('STATUS', 0);
        if (! ($currentStatus == QueuedTaskStateDataType::STATUS_RECEIVED || $currentStatus == QueuedTaskStateDataType::STATUS_QUEUED)) {
            $statusType = QueuedTaskStateDataType::fromValue($this->getWorkbench(), $currentStatus);
            throw new RuntimeException('Cannot start queued task "' . $queueItemUid . '": invalid status "' . $statusType->getLabelOfValue() . '"!');
        }
        
        $dataSheet->setCellValue('STATUS', 0, QueuedTaskStateDataType::STATUS_INPROGRESS);
        $dataSheet->setCellValue('QUEUE', 0, $this->getUid());
        $dataSheet->setCellValue('PID', 0, $this->getProcessId());
        
        $dataSheet->dataUpdate();
        
        return $dataSheet;
    }
    
    /**
     * Returns the process ID for this instance.
     *
     * @return int
     */
    protected function getProcessId() : int
    {
        if($this->processId !== null) {
            return $this->processId;
        }
        
        // 1. Preferred: built-in PHP (works on all platforms)
        if (function_exists('getmypid')) {
            $pid = getmypid();
            if ($pid !== false) {
                $this->processId = (int)$pid;
                return $this->processId;
            }
        }

        // 2. POSIX (Unix/Linux environments)
        if (function_exists('posix_getpid')) {
            $this->processId = (int)posix_getpid();
            return $this->processId;
        }

        // 3. Fallback: environment variable (some SAPIs)
        $pid = getenv('PID');
        if ($pid !== false && ctype_digit($pid)) {
            $this->processId = (int)$pid;
            return $this->processId;
        }

        // 4. Last resort: shell-based detection (avoid unless really needed)
        if (stripos(PHP_OS, 'WIN') === 0) {
            // Windows
            $output = [];
            @exec('echo %PROCESS_ID%', $output);
            if (!empty($output[0]) && ctype_digit($output[0])) {
                $this->processId = (int)$output[0];
                return $this->processId;
            }
        } else {
            // Unix-like
            $output = [];
            @exec('echo $$', $output);
            if (!empty($output[0]) && ctype_digit($output[0])) {
                $this->processId = (int)$output[0];
                return $this->processId;
            }
        }

        throw new RuntimeException('Unable to determine process ID');
    }

    /**
     * Returns true if PID is currently running, false if definitely not.
     */
    protected function isProcessRunning(?int $pid) : bool
    {
        if ($pid === null || $pid <= 0) {
            return false;
        }

        // Unix/Linux/macOS: safest API check
        if (function_exists('posix_kill') && stripos(PHP_OS, 'WIN') !== 0) {
            // signal 0 does not kill; it only checks process existence/permissions
            return @posix_kill($pid, 0);
        }

        if (stripos(PHP_OS, 'WIN') === 0) {
            $pid = (int)$pid;
            if ($pid <= 0) {
                return false;
            }

            $tasklist = (getenv('WINDIR') ?: 'C:\\Windows') . '\\System32\\tasklist.exe';
            $cmd = sprintf('"%s" /FI "PID eq %d" /FO CSV /NH', $tasklist, $pid);

            $output = [];
            $exitCode = 1;
            @exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || empty($output)) {
                return false;
            }

            // Running process rows come as CSV lines starting with a quote.
            foreach ($output as $line) {
                $line = trim($line);
                if ($line !== '' && isset($line[0]) && $line[0] === '"') {
                    return true;
                }
            }

            return false;
        }

        return true;
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
            
            // Save the logbook if there is one
            if ($result->getTask()->hasAction()) {
                $task = $result->getTask();
                $logbook = $task->getAction()->getLogBook($task);
            } else {
                $logbook = null;
            }

            $dataSheet->setCellValue('LOGBOOK', 0,  $logbook !== null ? $logbook->__toString() : '');
            $dataSheet->setCellValue('RESULT_CODE', 0, $result->getResponseCode());
            $dataSheet->setCellValue('RESULT', 0, $result->getMessage());
            $dataSheet->setCellValue('STATUS', 0, QueuedTaskStateDataType::STATUS_DONE);
            $dataSheet->setCellValue('PROCESSED_ON', 0, DateTimeDataType::now());
            $dataSheet->setCellValue('DURATION_MS', 0, $duration);
            $dataSheet->setCellValue('QUEUE', 0, $this->getUid());
            $dataSheet->setCellValue('PID', 0, null);
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
            $dataSheet->setCellValue('PID', 0, null);
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
    protected function enqueue(TaskInterface $task, array $topics = [], string $producer = null, string $messageId = null, string $channel = null) : DataSheetInterface
    {
        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUED_TASK');
        $dataSheet->getColumns()->addFromUidAttribute();
        
        if ($this->willSkipTaskIfAlreadyRunning()) {
            $parallelsSheet = $this->findParallelRuns($task, $producer);
            if (! $parallelsSheet->isEmpty()) {
                return $dataSheet;
            }
        }
        
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
        
        $row = [
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
        ];
        $row = array_merge($row, $this->extractRowTaskInfo($task));
        $dataSheet->addRow($row);
        
        if ($task instanceof ScheduledTask) {
            $dataSheet->setCellValue('SCHEDULER', 0, $task->getSchedulerUid());
        }
        
        $dataSheet->dataCreate();
        
        return $dataSheet;
    }

    protected function extractRowTaskInfo(TaskInterface $task) : array
    {
        $row = [];

        try {
            if ((null !== $sel = $task->getMetaObjectSelector()) && $sel->isAlias()) {
                $row['OBJECT_ALIAS'] = $sel->toString();
            } else {
                $row['OBJECT_ALIAS'] = $task->getMetaObject()->getAliasWithNamespace();
            }
        } catch (\Throwable $e) {
            // If anything goes wrong (i.e. object not found, save the task without this informaiton)
        }

        try {
            if ((null !== $sel = $task->getActionSelector()) && $sel->isAlias()) {
                $row['ACTION_ALIAS'] = $sel->toString();
            } else {
                $row['ACTION_ALIAS'] = $task->getAction()->getAliasWithNamespace();
            }
        } catch (\Throwable $e) {
            // If anything goes wrong (i.e. action not found, save the task without this informaiton)
        }

        return $row;
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

    protected function findParallelRuns(TaskInterface $task, string $producer) : DataSheetInterface
    {
        $sheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUED_TASK');
        $sheet->getColumns()
            ->addFromSystemAttributes()
            ->addMultiple([
                'STATUS',
                'PARENT_ITEM',
                'ENQUEUED_ON',
                'PID'
            ]);
        $sheet->getFilters()
            ->addConditionFromString('PRODUCER', $producer, ComparatorDataType::EQUALS)
            ->addConditionFromString('QUEUE', $this->getUid(), ComparatorDataType::EQUALS)
            ->addConditionFromString('STATUS', QueuedTaskStateDataType::STATUS_INPROGRESS, ComparatorDataType::EQUALS)
            ->addConditionFromString('TASK_UXON', $task->exportUxonObject()->toJson(), ComparatorDataType::EQUALS);

        $sheet->getSorters()->addFromString('CREATED_ON', 'DESC');

        $sheet->dataRead();
        
        // Check for timeouts.
        if($task instanceof ScheduledTask) {
            $timeOutSheet = $sheet->copy()->removeRows();
            $now = new \DateTime();
            $timeOutInterval = $task->getQueueTimeOutInterval();
            $maxTimeOutInterval = $task->getMaxQueueTimeOutInterval();
            
            foreach ($sheet->getRows() as $rowIdx => $row) {
                $pid = $row['PID'];
                $timeOutDate = new \DateTime($row['ENQUEUED_ON']);
                $timeOutDate->add($timeOutInterval);
                
                $maxTimeOutDate = new \DateTime($row['ENQUEUED_ON']);
                $maxTimeOutDate->add($maxTimeOutInterval);
                
                // Check if the run is timed out.
                if( $maxTimeOutDate <= $now ||
                    ($timeOutDate <= $now && $pid !== null && !$this->isProcessRunning($pid))
                ) {
                    $row['STATUS'] = QueuedTaskStateDataType::STATUS_TIMEOUT;
                    $row['PID'] = null;
                    $timeOutSheet->addRow($row);
                    $sheet->removeRow($rowIdx);
                }
            }

            if($timeOutSheet->countRows() > 0) {
                // Update status.
                $timeOutSheet->dataUpdate();
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
    
    /**
     *
     * @param OnQueueRunEvent $event
     */
    public function onRunPerformTask(OnQueueRunEvent $event)
    {
        // Ensure the task completes even if the HTTP connection is closed by the client
        // or a proxy timeout. Without this, PHP stops execution when the connection drops,
        // leaving the task permanently stuck in IN_PROGRESS state.
        ignore_user_abort(true);
        
        if ($event->getQueue() !== $this) {
            return;
        }
        
        try {
            $start = Debugger::getTimeMsNow();
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
            $result = $this->performTask($task);
            
            // If the task is a stream, read it completely here to make sure all generators
            // are run. If they produce errors, they should be handled as task/action errors
            // and not result-save errors.
            if ($result instanceof ResultMessageStreamInterface) {
                $result->getMessage();
            }
            
            // Save he result if no errors up-to now
            $this->saveResult($ds, $result, (Debugger::getTimeMsNow() - $start));
            $event->setResult($result);
        } catch (\Throwable $e) {
            if (! $e instanceof QueueRuntimeError) {
                $e = new QueueRuntimeError($this, 'Error in queue "' . $this->getName() . '": ' . $e->getMessage(), null, $e);
            }
            
            $this->getWorkbench()->getLogger()->logException($e, $this->getErrorLogLevel($e->getLogLevel()));
            
            $this->saveError($ds, $e, QueuedTaskStateDataType::STATUS_ERROR, (Debugger::getTimeMsNow() - $start));
            
            $result = ResultFactory::createErrorResult($task, $e);
            $result->setDataModified(true);
            $event->setResult($result);
        }
        return;
    }

    /**
     * Perform the task - e.g. handle it via workbench by default.
     * 
     * @param TaskInterface $task
     * @return ResultInterface
     */
    protected function performTask(TaskInterface $task) : ResultInterface
    {
        return $this->getWorkbench()->handle($task);
    }

    /**
     * @return bool
     */
    protected function willSkipTaskIfAlreadyRunning() : bool
    {
        return $this->skipTaskIfAlreadyRunning;
    }
    
    /**
     * Set to TRUE to double-check if exactly this task is already running and prevent parallel launches
     * 
     * @uxon-property skip_task_if_already_running
     * @uxon-type bool
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return TaskQueueInterface
     */
    protected function setSkipTaskIfAlreadyRunning(bool $trueOrFalse) : TaskQueueInterface
    {
        $this->skipTaskIfAlreadyRunning = $trueOrFalse;
        return $this;
    }
}