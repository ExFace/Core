<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\DataTypes\QueuedTaskStateDataType;
use exface\Core\Factories\TaskFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Queue\OnQueueRunEvent;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\Factories\ResultFactory;
use exface\Core\Exceptions\LogicException;
use exface\Core\CommonLogic\Tasks\ResultError;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Interfaces\Actions\iModifyContext;
use exface\Core\Interfaces\Actions\iModifyData;

/**
 * Performs a task that is saved in a task queue.
 * 
 * @author Ralf Mulansky
 *
 */
class RunQueuedTasks extends AbstractAction implements iModifyData
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::PLAY);
        $this->setName('Run Task(s)');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputData = $this->getInputDataSheet($task);
        
        if ($inputData->hasUidColumn(true) === false) {
            throw new DataSheetColumnNotFoundError($inputData, "UID column not present in data sheet with meta object '{$inputData->getMetaObject()->getAliasWithNamespace()}'");
        }
        
        $tasksDs = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUED_TASK');
        $tasksDs->getColumns()->addFromExpression('QUEUE');
        $tasksDs->getColumns()->addFromExpression('TASK_UXON');
        $tasksDs->getFilters()->addConditionFromValueArray('STATUS', [QueuedTaskStateDataType::STATUS_RECEIVED, QueuedTaskStateDataType::STATUS_QUEUED]);
        if ($inputData->getMetaObject()->is('exface.Core.QUEUED_TASK')) {
            $tasksDs->getFilters()->addConditionFromValueArray($inputData->getUidColumn()->getAttributeAlias(), $inputData->getUidColumn()->getValues());            
        } elseif ($inputData->getMetaObject()->is('exface.Core.QUEUE')) {
            $tasksDs->getFilters()->addConditionFromValueArray('QUEUE', $inputData->getUidColumn()->getValues());
        } else {
            throw new ActionInputInvalidObjectError($this, "Meta object '{$inputData->getMetaObject()->getAliasWithNamespace()}' is not suitable for action '{$this->getAliasWithNamespace()}'");
        }
        $tasksDs->dataRead();
        
        $queues = $this->getQueues($tasksDs);
        $tasks = $this->getTasksToRun($tasksDs);
        
        if (count($queues) !== count($tasks)) {
            throw new ActionRuntimeError('Cannot find queues for all pending tasks!');
        }
        
        $success = [];
        $failed = [];
        
        foreach ($tasks as $uid => $task) {
            $event = $this->getWorkbench()->eventManager()->dispatch(new OnQueueRunEvent($queues[$uid], $task, $uid));
            if (! $event->hasResult()) {
                throw new LogicException("Performing the task with UID '{$uid}' did not produce any result or error");
            }
            $result = $event->getResult();
            if ($result instanceof ResultError) {
                $failed[] = $result;
            } else {
                $success[] = $event->getResult();
            }
        }
        
        $successCount = count($success);
        $failedCount = count($failed);
        $message = '';
        if ($successCount > 0) {
            $message .= "{$successCount} Task(s) run successfully. ";
        }
        if ($failedCount > 0) {
            $message .= "{$failedCount} Task(s) failed. ";
        }
        return ResultFactory::createMessageResult($task, $message);
    }
    
    /**
     * Returns an array containing all queue objects for the given uids in the data sheet. Keys in this array are the uids.
     * 
     * @param DataSheetInterface $ds
     * @return array
     */
    protected function getQueues(DataSheetInterface $ds) : array
    {
        $queueIds = $ds->getColumnValues('QUEUE');
        $taskIds = $ds->getColumnValues('UID');
        $queueDs = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUE');
        $queueDs->getColumns()->addMultiple([
            'UID',
            'ALIAS',
            'APP',
            'NAME',
            'CONFIG_UXON',
            'ALLOW_MULTI_QUEUE_HANDLING',
            'PROTOTYPE_CLASS'
        ]);
        $queueDs->getFilters()->addConditionFromValueArray('UID', $queueIds);
        $queueDs->dataRead();
        $queues = [];
        foreach ($taskIds as $idx => $id) {
            $queueId = $queueIds[$idx];
            $row = $queueDs->getRowByColumnValue('UID', $queueId);
            $class = '\\' . ltrim($row['PROTOTYPE_CLASS'], "\\");
            $uxon = UxonObject::fromJson($row['CONFIG_UXON'] ?? '{}');
            $uxon->setProperty('allow_other_queues_to_handle_same_tasks', $row['ALLOW_MULTI_QUEUE_HANDLING']);
            $queue = new $class($this->getWorkbench(), $row['UID'], $row['ALIAS'], $row['APP'], $row['NAME'], $uxon);            
            $queues[$id] = $queue;         
        }
        return $queues;
    }
    
    /**
     * Returns an array containing all tasks to run as objects for the given uids in the data sheet. Keys in this array are the uids.
     *
     * @param DataSheetInterface $ds
     * @return array
     */
    protected function getTasksToRun(DataSheetInterface $ds) : array
    {
        $taskIds = $ds->getColumnValues('UID');
        $tasks = [];
        foreach ($taskIds as $id) {
            $taskString = $ds->getRowByColumnValue('UID', $id)['TASK_UXON'];
            $taskUxon = UxonObject::fromJson($taskString);
            $taskToRun = TaskFactory::createEmpty($this->getWorkbench());
            $taskToRun->importUxonObject($taskUxon);
            $tasks[$id] = $taskToRun;
        }
        return $tasks;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::isTriggerWidgetRequired()
     */
    public function isTriggerWidgetRequired() : ?bool
    {
        return false;
    }
}