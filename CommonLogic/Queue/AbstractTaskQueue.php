<?php
namespace exface\Core\CommonLogic\Queue;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\TaskQueueInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * Base class for default queue prototypes
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractTaskQueue implements TaskQueueInterface, WorkbenchDependantInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $name = null;
    
    private $alias = null;
    
    private $appSelector = null;
    
    private $configUxon = null;
    
    private $topicsMatcher = null;
    
    private $allowOtherQueuesToHandleSameTasks = false;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $alias
     * @param AppSelectorInterface|string $appSelector
     * @param string $name
     * @param UxonObject $configUxon
     */
    public function __construct(WorkbenchInterface $workbench, string $alias, $appSelector = null, string $name = null, UxonObject $configUxon = null)
    {
        $this->workbench = $workbench;
        $this->alias = $alias;
        $this->appSelector = $appSelector;
        $this->name = $name;
        $this->configUxon = $configUxon;
        
        if ($configUxon !== null) {
            $this->importUxonObject($configUxon);
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TaskQueueInterface::canHandle()
     */
    public function canHandle(TaskInterface $task, array $topics = [], string $producer = null): bool
    {
        return $this->matchTopics($topics);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getWorkbench();
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param string[] $topics
     * @param string $producer
     * @return DataSheetInterface
     */
    protected function createQueueDataSheet(TaskInterface $task, array $topics = [], string $producer = null) : DataSheetInterface
    {
        $dataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.TASK_QUEUE');
        $dataSheet->getColumns()->addFromUidAttribute();
        if ($task->hasParameter('assignedOn')) {
            $assignedOn = $task->getParameter('assignedOn');
        } else {
            $assignedOn = DateTimeDataType::now();
        }
        $dataSheet->addRow([
            'TASK_UXON' => $task->exportUxonObject()->toJson(),
            'STATUS' => 10, // TODO QueuedTaskStateDataType::STATUS_QUEUED,
            'OWNER' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
            'PRODUCER' => $producer,
            'TASK_ASSIGNED_ON' => $assignedOn,
            'TOPICS' => implode(', ', $topics)
        ]);
        return $dataSheet;
    }
    
    public function getName(): string
    {
        return $this->name ?? lcfirst(str_replace('_', ' ', mb_strtolower($this->alias)));
    }

    public function getNamespace()
    {
        return $this->getApp()->getAliasWithNamespace();
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getAliasWithNamespace()
    {
        return $this->getNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->getAlias();
    }

    public function exportUxonObject()
    {
        return $this->configUxon;
    }
    
    public function getApp() : ?AppInterface
    {
        return $this->getWorkbench()->getApp($this->appSelector);
    }
    
    /**
     * This queue will handle tasks matching these topics
     * 
     * @uxon-property topics
     * @uxon-type object
     * @uxon-template {"all_of": [""], "one_of": [""]}
     * 
     * @param UxonObject $uxon
     * @return TaskQueueInterface
     */
    protected function setTopics(UxonObject $uxon) : TaskQueueInterface
    {
        $this->topicsMatcher = $uxon->toArray();
        return $this;
    }
    
    /**
     * 
     * @param array $taskTopics
     * @return bool
     */
    protected function matchTopics(array $taskTopics) : bool
    {
        $result = false;
        
        foreach ($this->topicsMatcher ?? [] as $op => $topics) {
            switch (strtolower($op)) {
                case 'all_of': 
                    $result = empty(array_diff($topics, $taskTopics));
                    break;
                case 'one_of':
                    $result = ! empty(array_intersect($topics, $taskTopics));
                    break;
            }
        }
        
        return $result;
    }
    
    /**
     * Set to `true` to allow multiple queues to handle the same task
     * 
     * @uxon-property allow_other_queues_to_handle_same_tasks
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return TaskQueueInterface
     */
    protected function setAllowOtherQueuesToHandleSameTasks(bool $trueOrFalse) : TaskQueueInterface
    {
        $this->allowOtherQueuesToHandleSameTasks = $trueOrFalse;
        return $this;
    }
    
    public function getAllowOtherQueuesToHandleSameTasks() : bool
    {
        return $this->allowOtherQueuesToHandleSameTasks;
    }
}