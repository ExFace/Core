<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\TaskHandlerInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\TaskQueueInterface;
use exface\Core\Exceptions\RuntimeException;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskQueueRouter implements TaskHandlerInterface, WorkbenchDependantInterface
{
    private $workbench = null;
    
    private $queues = null;
    
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
     * @see \exface\Core\Interfaces\TaskHandlerInterface::handle()
     */
    public function handle(TaskInterface $task, array $topics = [], string $producer = null): ResultInterface
    {
        foreach ($this->getQueues() as $queue) {
            $handlers = [];
            if ($queue->canHandle($task, $topics, $producer)) {
                $handlers = $queue;
            }
        }
        
        switch (count($handlers)) {
            case 0: throw new RuntimeException('No queue found to handle a task from provider "' . $producer . '" with topics "' . implode(', ', $topics) . '"!');
            case 1: break;
            default:
                foreach ($handlers as $queue) {
                    if ($queue->getAllowOtherQueuesToHandleSameTasks() === false) {
                        throw new RuntimeException('Multiple queues found for task, but queue "' . $queue->getAliasWithNamespace() . '" does forbids multiqueue handling!');
                    }
                }
        }
        
        // TODO what is the result of putting the task into multiple queues? Currently it would
        // be the result of the last queue.
        foreach ($handlers as $queue) {
            $result = $queue->handle($task, $topics, $producer);
        }
        
        return $result;
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
                $uxon = $row['CONFIG_UXON'] ?? new UxonObject();
                $uxon->setProperty('allow_other_queues_to_handle_same_tasks', $row['ALLOW_MULTI_QUEUE_HANDLING']);
                $queue = new $class($this->getWorkbench(), $row['ALIAS'], $row['APP'], $row['NAME'], $uxon);
                $this->queues[] = $queue;
            }
        }
        return $this->queues;
    }
}