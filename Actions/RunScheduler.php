<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use exface\Core\CommonLogic\AbstractActionDeferred;
use exface\Core\CommonLogic\Tasks\ResultMessageStream;
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

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class RunScheduler extends AbstractActionDeferred implements iCanBeCalledFromCLI
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
        return [];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performDeferred()
     */
    protected function performDeferred() : \Generator
    {
        yield 'Running the scheduler at ' . DateTimeDataType::formatDateLocalized((new \DateTime()), $this->getWorkbench()) . ':' . PHP_EOL;
        $scheduledDs = $this->getScheduledTasks();
        $cnt = 0;
        $router = new TaskQueueBroker($this->getWorkbench());
        foreach ($scheduledDs->getRows() as $rowNo => $row) {
            yield PHP_EOL . 'Task "' . $row['NAME'] . '": ';
            
            if ($row['DISABLED']) {
                yield 'disabled.' . PHP_EOL;
                continue;
            }
            
            $lastRunTime = new \DateTime($row['LAST_RUN'] ?? $row['FIRST_RUN']);
            if (CronDataType::isDue($row['SCHEDULE'], $lastRunTime)) {
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
                    $actionSelector = $actionUxon->getProperty('alias');
                    $taskUxon = UxonObject::fromJson($row['TASK_UXON'] ?? '');
                    $taskUxon->setProperty('action', $actionSelector);
                    $task = new ScheduledTask($this->getWorkbench(), $taskUxon, $row['UID']);
                    $result = $router->handle($task, explode(',', $row['QUEUE_TOPICS']), $row['UID'], UUIDDataType::generateShortId(8, $rowNo), 'Scheduler');
                    if ($result instanceof ResultError) {
                        yield 'failed. ' . $result->getMessage();
                    } else {
                        yield $result->getMessage() ?: 'done.';
                    }
                } catch (\Throwable $e) {
                    // TODO
                    yield 'failed. ' . $e->getMessage() . ' in ' . $e->getFile() . ' at line ' . $e->getLine();
                } 
            } else {
                yield 'not due at (next run at ' . DateTimeDataType::formatDateLocalized(CronDataType::findNextRunTime($row['SCHEDULE'], $lastRunTime), $this->getWorkbench()) . ').';
            }
            
            yield PHP_EOL;
        }
        if ($cnt === 0) {
            yield 'No scheduled tasks to run now' . PHP_EOL;
        }
    }
    
    protected function getScheduledTasks() : DataSheetInterface
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
            'DISABLED'
        ]);
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
        return [];
    }

}