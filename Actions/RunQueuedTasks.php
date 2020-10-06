<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\DataTypes\QueuedTaskStateDataType;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\InternalError;
use exface\Core\Factories\TaskFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Queue\OnQueueRunEvent;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\Factories\ConditionFactory;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Factories\ResultFactory;
use exface\Core\Exceptions\RuntimeException;

/**
 * Performs a task that is saved in a task queue.
 * 
 * @author Ralf Mulansky
 *
 */
class RunQueuedTasks extends AbstractAction
{    
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::PLAY);
        $this->setName('Run Task(s)');
    }
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputData = $this->getInputDataSheet($task);
        if ($inputData->hasUidColumn(true) === false) {
            throw new DataSheetColumnNotFoundError($inputData, "UID column not present in data sheet with meta object '{$inputData->getMetaObject()->getAliasWithNamespace()}'");
        }
        $tasksDs = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUED_TASK');
        $tasksDs->getColumns()->addFromExpression('QUEUE');
        $tasksDs->getColumns()->addFromExpression('TASK_UXON');
        if ($inputData->getMetaObject()->is('exface.Core.QUEUED_TASK')) {
            $tasksDs->getFilters()->addConditionFromValueArray($inputData->getUidColumn()->getAttributeAlias(), $inputData->getUidColumn()->getValues());            
        } elseif ($inputData->getMetaObject()->is('exface.Core.QUEUE')) {
            $tasksDs->getFilters()->addConditionFromValueArray('QUEUE', $inputData->getUidColumn()->getValues());
            $tasksDs->getFilters()->addConditionFromString('STATUS', QueuedTaskStateDataType::STATUS_QUEUED);
        } else {
            throw new ActionInputInvalidObjectError($this, "Meta object '{$inputData->getMetaObject()->getAliasWithNamespace()}' is not suitable for action '{$this->getAliasWithNamespace()}'");
        }
        $tasksDs->dataRead();
        $queues = $this->getQueues($tasksDs);
        $tasks = $this->getTasksToRun($tasksDs);
        if (count($queues) !== count($tasks)) {
            //TODO
            throw new RuntimeException('An error occured in the implementation!');
        }
        $results = [];
        foreach ($tasks as $uid => $task) {
            $event = $this->getWorkbench()->eventManager()->dispatch(new OnQueueRunEvent($queues[$uid], $task, $uid));
            $results[] = $event->getResult();
        }
        $count = count($results);
        return ResultFactory::createMessageResult($task, "{$count} Task(s) run");
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