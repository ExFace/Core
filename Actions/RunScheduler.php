<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Actions\ServiceParameter;
use exface\Core\CommonLogic\Debugger\LogBooks\ActionLogBook;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use exface\Core\CommonLogic\AbstractActionDeferred;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\CronDataType;
use exface\Core\CommonLogic\Tasks\ScheduledTask;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Queue\TaskQueueBroker;
use exface\Core\DataTypes\UUIDDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\CommonLogic\Tasks\ResultError;
use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Exceptions\Queues\SchedulerError;
use exface\Core\DataTypes\BooleanDataType;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class RunScheduler extends AbstractActionDeferred implements iCanBeCalledFromCLI, iModifyData
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon('clock-o');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performImmediately()
     */
    protected function performImmediately(TaskInterface $task, DataTransactionInterface $transaction, ResultMessageStreamInterface $result) : array
    {
        $taskNames = [];
        $ignoreSchedule = false;
        if ($task->hasParameter('task')) {
            $taskNames[] = $task->getParameter('task');
            $ignoreSchedule = true;
        }
        return [$taskNames, $ignoreSchedule, $this->getLogBook($task)];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performDeferred()
     */
    protected function performDeferred(array $taskNames = [], bool $ignoreSchedule = false, ?ActionLogBook $logbook = null) : \Generator
    {
        yield 'Running the scheduler at ' . DateTimeDataType::formatDateLocalized((new \DateTime()), $this->getWorkbench()) . ':' . PHP_EOL;
        $scheduledDs = $this->getScheduledTasks($taskNames);
        $cnt = 0;
        $router = new TaskQueueBroker($this->getWorkbench());
        $logbook?->addLine('Checking `' . $scheduledDs->countRows() . '` scheduled tasks');
        $logbook?->addIndent(+1);
        foreach ($scheduledDs->getRows() as $rowNo => $row) {
            $msg = 'Task "' . $row['NAME'] . '": ';
            yield PHP_EOL . $msg;
                $logbook?->addLine('Task "' . $row['NAME'] . '": ');
            
            if (BooleanDataType::cast($row['ENABLED']) !== true) {
                yield 'disabled.' . PHP_EOL;
                $logbook?->continueLine('disabled');
                continue;
            }
            
            $lastRunTime = new \DateTime($row['LAST_RUN'] ?? $row['FIRST_RUN']);
            if ($ignoreSchedule || CronDataType::isDue($row['SCHEDULE'], $lastRunTime)) {
                $cnt++;
                try {
                    
                    $updSheet = DataSheetFactory::createFromObject($scheduledDs->getMetaObject());
                    $updSheet->addRow([
                        'UID' => $row['UID'],
                        'LAST_RUN' => DateTimeDataType::now(),
                        'MODIFIED_ON' => $row['MODIFIED_ON']
                    ]);
                    $updSheet->dataUpdate();
                    
                    $actionUxon = UxonObject::fromJson($row['ACTION_UXON'] ?? '');
                    $taskUxon = UxonObject::fromJson($row['TASK_UXON'] ?? '');
                    if (!empty($row['ACTION_UXON'])) {
                        $actionSelector = $actionUxon->getProperty('alias');
                        $taskUxon->setProperty('action', $actionSelector);
                    }
                    $task = new ScheduledTask($this->getWorkbench(), $taskUxon, $row['UID']);
                    $result = $router->handle($task, explode(',', $row['QUEUE_TOPICS']), $row['UID'], UUIDDataType::generateShortId(8, $rowNo), 'Scheduler');
                    if ($result instanceof ResultError) {
                        $msg = 'failed. ' . $result->getMessage();
                    } else {
                        $msg = $result->getMessage() ?: 'done.';
                    }
                } catch (\Throwable $e) {
                    $this->getWorkbench()->getLogger()->logException(new SchedulerError('Error in scheduled task "' . $row['NAME'] . '": ' . $e->getMessage(), null, $e));
                    $msg = 'failed. ' . $e->getMessage() . ' in ' . $e->getFile() . ' at line ' . $e->getLine();
                }
                $logbook->continueLine($msg);
                yield $msg;
            } else {
                $msg = 'not due (next run at ' . DateTimeDataType::formatDateLocalized(CronDataType::findNextRunTime($row['SCHEDULE'], $lastRunTime), $this->getWorkbench()) . ').';
                yield $msg;
                $logbook?->continueLine($msg);
            }
            
            yield PHP_EOL;
        }
        if ($cnt === 0) {
            yield 'No scheduled tasks to run now' . PHP_EOL;
        }
    }

    /**
     * @param string[]|null $taskNames
     * @return DataSheetInterface
     */
    protected function getScheduledTasks(?array $taskNames = null) : DataSheetInterface
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.SCHEDULER');
        $ds->getColumns()->addMultiple([
            'UID',
            'NAME',
            'ACTION_UXON',
            'TASK_UXON',
            'SCHEDULE',
            'FIRST_RUN',
            'LAST_RUN',
            'QUEUE_TOPICS',
            'MODIFIED_ON',
            'ENABLED'
        ]);
        if (! empty($taskNames)) {
            $ds->getFilters()->addConditionFromValueArray('NAME', $taskNames);
        }
        $ds->dataRead();
        return $ds;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliArguments()
     */
    public function getCliArguments(): array
    {
        return [];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliOptions()
     */
    public function getCliOptions(): array
    {
        return [
            new ServiceParameter($this, new UxonObject([
                'name' => 'task',
                'description' => 'Name of the scheduler item to run - if set, it will be forced to run regardless of its schedule'
            ]))
        ];
    }

}