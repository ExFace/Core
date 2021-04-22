<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\TaskQueueInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\TaskQueueBrokerInterface;
use exface\Core\DataTypes\QueuedTaskStateDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Exceptions\InternalError;
use exface\Core\Events\Workbench\OnCleanUpEvent;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Default implementation of the TaskQueueBrokerInterface.
 * 
 * Instantiates all task queues and calls `canHandle()` on every queue to determine,
 * which one is responsible for a task. If no queue can be found, the task is saved
 * with status `20 Orphaned` without a queue relation.
 * 
 * The broker also calls the `cleanUp()` method of every queue whenever the `OnCleanUpEvent`
 * of the workbench is fired.
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskQueueBroker implements TaskQueueBrokerInterface, WorkbenchDependantInterface
{
    const CLEANUP_AREA_QUEUES = 'queues';
    
    private $workbench = null;
    
    private $queues = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TaskQueueBrokerInterface::handle()
     */
    public function handle(TaskInterface $task, array $topics = [], string $producer = null, string $messageId = null, string $channel = null): ResultInterface
    {
        try {
            $handlers = $this->findQueues($task, $topics, $producer);
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::ALERT);
            $this->saveOrphan($task, $topics, $producer, $messageId, $channel, $e);
            $result = ResultFactory::createErrorResult($task, $e);
            return $result;
        }
        
        // TODO what is the result of putting the task into multiple queues? Currently it would
        // be the result of the last queue.
        foreach ($handlers as $queue) {
            $result = $queue->handle($task, $topics, $producer, $messageId, $channel);
        }
        
        return $result;
    }
    
    protected function findQueues(TaskInterface $task, array $topics, string $producer = null) : array
    {
        $handlers = [];
        $fallbackHandlers = [];
        foreach ($this->getQueues() as $queue) {
            if ($queue->canHandleAnyTask()) {
                $fallbackHandlers[] = $queue;
            } elseif ($queue->canHandle($task, $topics, $producer)) {
                $handlers[] = $queue;
            }
        }
        
        if (empty($handlers)) {
            $handlers = $fallbackHandlers;
        }
        
        switch (count($handlers)) {
            case 0:
                throw new RuntimeException('No queue found to handle a task from provider "' . $producer . '" with topics "' . implode(', ', $topics) . '"!');
            case 1: break;
            default:
                foreach ($handlers as $queue) {
                    if ($queue->getAllowOtherQueuesToHandleSameTasks() === false) {
                        throw new RuntimeException('Multiple queues found for task, but queue "' . $queue->getAliasWithNamespace() . '" does forbids multiqueue handling!');
                    }
                }
        }
        return $handlers;
    }
    
    /**
     * 
     * @return TaskQueueInterface[]
     */
    protected function getQueues() : array
    {
        if ($this->queues === null) {
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUE');
            $ds->getColumns()->addMultiple([
                'ALIAS',
                'APP',
                'NAME',
                'CONFIG_UXON',
                'ALLOW_MULTI_QUEUE_HANDLING', 
                'PROTOTYPE_CLASS'
            ]);
            $ds->dataRead();
            
            foreach ($ds->getRows() as $row) {
                $class = '\\' . ltrim($row['PROTOTYPE_CLASS'], "\\");
                $uxon = UxonObject::fromJson($row['CONFIG_UXON'] ?? '{}');
                $uxon->setProperty('allow_other_queues_to_handle_same_tasks', $row['ALLOW_MULTI_QUEUE_HANDLING']);
                $queue = new $class($this->getWorkbench(), $row['UID'], $row['ALIAS'], $row['APP'], $row['NAME'], $uxon);
                $this->queues[] = $queue;
            }
        }
        return $this->queues;
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param string[] $topics
     * @param string $producer
     * @param string $messageId
     * @param string $channel
     * @param ExceptionInterface $exception
     * @return DataSheetInterface
     */
    protected function saveOrphan(TaskInterface $task, array $topics, string $producer = null, string $messageId = null, string $channel = null, ExceptionInterface $exception = null) : DataSheetInterface
    {
        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUED_TASK');
        
        if ($task->hasParameter('assignedOn')) {
            $assignedOn = $task->getParameter('assignedOn');
        } else {
            $assignedOn = DateTimeDataType::now();
        }
        
        $userAgent = null;
        if ($task instanceof HttpTaskInterface) {
            $request = $task->getHttpRequest();
            if ($request->hasHeader('User-Agent')) {
                $userAgent = $request->getHeader('User-Agent')[0];
            }
        }
        
        $dataSheet->addRow([
            'TASK_UXON' => $task->exportUxonObject()->toJson(),
            'STATUS' => QueuedTaskStateDataType::STATUS_ORPHANED,
            'OWNER' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
            'PRODUCER' => $producer,
            'MESSAGE_ID' => $messageId,
            'TASK_ASSIGNED_ON' => $assignedOn,
            'TOPICS' => implode(', ', $topics),
            'CHANNEL' => $channel,
            'USER_AGENT' => $userAgent
        ]);
        
        if ($exception) {
            if (! ($exception instanceof ExceptionInterface)) {
                $exception = new InternalError($exception->getMessage(), null, $exception);
            }
            
            $dataSheet->setCellValue('ERROR_MESSAGE', 0, $exception->getMessage());
            $dataSheet->setCellValue('ERROR_LOGID', 0, $exception->getId());
        }
        
        $dataSheet->dataCreate();
        
        return $dataSheet;
    }
    
    /**
     * 
     * @param OnCleanUpEvent $event
     * @return void
     */
    public static function onCleanUp(OnCleanUpEvent $event)
    {
        if (! $event->isAreaToBeCleaned(self::CLEANUP_AREA_QUEUES)) {
            return;
        }
        
        $broker = new self($event->getWorkbench());
        foreach ($broker->getQueues() as $queue) {
            $event->addResultMessage($queue->cleanUp());
        }
        return;
    }
}