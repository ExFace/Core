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
            throw new DataSheetColumnNotFoundError($inputData, "UID column not present in data sheet with meta obejt '{$inputData->getMetaObject()->getAliasWithNamespace()}'");
        }
        $tasksDs = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUED_TASK');
        $tasksDs->getColumns()->addFromExpression('QUEUE');
        $tasksDs->getColumns()->addFromExpression('TASK_UXON');
        if ($inputData->getMetaObject()->getAliasWithNamespace() === 'exface.Core.QUEUED_TASK') {
            $tasksDs->getFilters()->addConditionFromValueArray($inputData->getUidColumn()->getAttributeAlias(), $inputData->getUidColumn()->getValues());
            
        } elseif ($inputData->getMetaObject()->getAliasWithNamespace() === 'exface.Core.QUEUE') {            
            $cdGroup = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_AND, $tasksDs->getMetaObject());
            $cdGroup->addConditionFromValueArray('QUEUE', $inputData->getUidColumn()->getValues());
            $cdGroup->addConditionFromString('STATUS', QueuedTaskStateDataType::STATUS_QUEUED);
            $tasksDs->getFilters()->addNestedGroup($cdGroup);
        } else {
            throw new ActionInputInvalidObjectError($this, "Meta object '{$inputData->getMetaObject()->getAliasWithNamespace()}' is not suitable for action '{$this->getAliasWithNamespace()}'");
        }
        $tasksDs->dataRead();
        $tasksToRunData = $this->getTasksToRunData($tasksDs);
        $results = [];
        foreach ($tasksToRunData as $uid => $taskData) {
            $event = $this->getWorkbench()->eventManager()->dispatch(new OnQueueRunEvent($taskData['queue'], $taskData['task'], $uid));
            $results[] = $event->getResult();
        }
        $count = count($results);
        return ResultFactory::createMessageResult($task, "{$count} Task(s) run");
    }
    
    /**
     * Returns a 3dimensional array containing the saved task in a queue and the queue responsible for that task.
     * The array looks like that: array[uid]= ['task' => TaskObject, 'queue' => QueueObject].
     * 
     * @param DataSheetInterface $ds
     * @return array
     */
    protected function getTasksToRunData(DataSheetInterface $ds) : array
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
        $taskData = [];
        foreach ($taskIds as $idx => $id) {
            $queueId = $queueIds[$idx];
            $row = $queueDs->getRowByColumnValue('UID', $queueId);
            $class = '\\' . ltrim($row['PROTOTYPE_CLASS'], "\\");
            $uxon = UxonObject::fromJson($row['CONFIG_UXON'] ?? '{}');
            $uxon->setProperty('allow_other_queues_to_handle_same_tasks', $row['ALLOW_MULTI_QUEUE_HANDLING']);
            $queue = new $class($this->getWorkbench(), $row['UID'], $row['ALIAS'], $row['APP'], $row['NAME'], $uxon);
            $taskString = $ds->getRowByColumnValue('UID', $id)['TASK_UXON'];
            $taskUxon = UxonObject::fromJson($taskString);
            $taskToRun = TaskFactory::createEmpty($this->getWorkbench());
            $taskToRun->importUxonObject($taskUxon);
            $taskData[$id] = [
                'task' => $taskToRun,
                'queue' => $queue
            ];            
        }
        return $taskData;
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